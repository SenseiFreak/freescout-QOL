// Run with Playwright on NODE_PATH. APIs are mocked; no provider credentials are used.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const moduleRoot = path.resolve(__dirname, '..');
let html = fs.readFileSync(path.join(moduleRoot, 'Resources/views/calendar/index.blade.php'), 'utf8')
    .replace(/@foreach[\s\S]*?@endforeach/g, '')
    .replace(/@(?:extends|section|include)\([^\n]*\)/g, '')
    .replace(/@endsection|@endif/g, '').replace(/@if\s*\(Auth::user\(\)->isAdmin\(\)\)/g, '')
    .replace(/\{\{\s*__\('([^']*)'\)\s*\}\}/g, '$1')
    .replace(/\{\{\s*route\('([^']*)'\)\s*\}\}/g, (_, name) => '/' + name)
    .replace(/\{\{\s*asset\('([^']*)'\)\s*\}\}/g, (_, name) => '/' + name)
    .replace(/\{\{[^}]+\}\}/g, '');
html = '<!doctype html><meta charset="utf-8">' + html;

(async function () {
    let browser;
    try { browser = await chromium.launch({ headless: true }); }
    catch (_) { browser = await chromium.launch({ channel: 'msedge', headless: true }); }
    try {
        const page = await browser.newPage();
        let mode = 'google', queries = [], saved;
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            if (url.pathname.endsWith('.js')) return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(moduleRoot, 'Public/js', path.basename(url.pathname)), 'utf8') });
            if (url.pathname.endsWith('.css')) return route.fulfill({ body: '' });
            if (url.pathname === '/qol.calendar.calendars') {
                if (mode === 'failure') return route.fulfill({ status: 502, json: { message: 'Reconnect the account.' } });
                return route.fulfill({ json: [{ id: 'first', name: 'First calendar', writable: true }, { id: 'preferred', name: 'My chosen calendar', writable: true }] });
            }
            if (url.pathname === '/qol.calendar.events') { queries.push(Object.fromEntries(url.searchParams)); return route.fulfill({ json: [] }); }
            if (url.pathname === '/qol.calendar.save') { saved = route.request().postDataJSON(); return route.fulfill({ json: { ok: true } }); }
            let body = html.replace('data-default-source=""', 'data-default-source="' + (mode === 'local' ? 'local' : 'google') + '"')
                .replace('data-default-calendar=""', 'data-default-calendar="' + (mode === 'missing' ? 'deleted' : 'preferred') + '"')
                .replace('data-ticket=""', 'data-ticket="42"');
            if (mode !== 'disconnected') body = body.replace('<option value="local">', '<option value="google">Google</option><option value="local" selected>');
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body });
        });
        await page.goto('https://calendar.test/');
        await page.waitForFunction(() => document.querySelector('#qc-dialog').open);
        assert.equal(await page.inputValue('#qc-source'), 'google');
        assert.equal(await page.inputValue('#qc-calendar'), 'preferred');
        assert.equal(queries[0].calendar, 'preferred');
        assert.equal(queries.some(q => q.source === 'local'), false);
        await page.fill('#qc-title', 'Scheduled ticket');
        await page.click('#qc-submit');
        await page.waitForFunction(() => !document.querySelector('#qc-dialog').open);
        assert.equal(saved.source, 'google'); assert.equal(saved.calendar, 'preferred'); assert.equal(saved.ticket, '42');
        for (const [nextMode, message] of [['missing', 'Your default calendar is unavailable'], ['disconnected', 'account is disconnected'], ['failure', 'Reconnect the account.']]) {
            mode = nextMode; queries = [];
            await page.goto('https://calendar.test/');
            await page.waitForFunction(text => document.querySelector('#qc-status').textContent.includes(text), message);
            assert.equal(await page.locator('#qc-dialog').evaluate(el => el.open), false, 'Do not schedule tickets into an unintended calendar');
            if (mode === 'missing' || mode === 'failure') assert.equal(queries.length, 0);
        }
        mode = 'local'; await page.goto('https://calendar.test/');
        await page.waitForFunction(() => document.querySelector('#qc-dialog').open);
        assert.equal(await page.inputValue('#qc-source'), 'local');
        assert.deepEqual(errors, []);
        console.log('PASS: preferred provider/calendar initialization, ticket destination, local default, missing calendar, disconnected account, provider failure.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
