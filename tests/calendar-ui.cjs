// Run with Playwright available via NODE_PATH: node tests/calendar-ui.cjs
// Uses mocked APIs; this does not replace a live FreeScout/OAuth integration test.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');

async function main() {
    fs.mkdirSync(path.join(root, '.calendar-tools'), { recursive: true });
    let html = fs.readFileSync(path.join(root, 'resources/views/calendar/index.blade.php'), 'utf8');
    html = html.replace(/@foreach[\s\S]*?@endforeach/g, '')
        .replace(/@(?:extends|section|include)\([^\n]*\)/g, '')
        .replace(/@endsection|@endif/g, '').replace(/@if\s*\(Auth::user\(\)->isAdmin\(\)\)/g, '').replace(/@if\([^\n]*?\)/g, '')
        .replace(/\{\{\s*__\('([^']*)'\)\s*\}\}/g, '$1')
        .replace(/\{\{\s*route\('([^']*)'\)\s*\}\}/g, (_, name) => '/' + name)
        .replace(/\{\{\s*asset\('([^']*)'\)\s*\}\}/g, (_, name) => '/' + name)
        .replace(/\{\{[^}]+\}\}/g, '');
    html = '<!doctype html><meta charset="utf-8"><style>body{font:14px Arial,sans-serif}.form-control{box-sizing:border-box;width:100%;padding:8px;border:1px solid #ccd5df;border-radius:4px}.btn{padding:8px 12px;border:1px solid #ccd5df;border-radius:4px;background:white;cursor:pointer}.btn-primary{background:#0078d4;color:white;border-color:#0078d4}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style>' + html;
    html = html.replace('data-token=""', 'data-token="test-csrf"')
        .replace('<option value="local">', '<option value="google">Google</option><option value="local" selected>');
    let browser;
    try { browser = await chromium.launch({ headless: true }); }
    catch (_) { browser = await chromium.launch({ channel: 'msedge', headless: true }); }
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
        const errors = [], saves = [];
        page.on('pageerror', error => errors.push(error.message));
        const now = new Date(), ymd = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        const events = [
            { id: 1, title: '<img src=x onerror=alert(1)>', start: ymd + 'T09:00:00', end: ymd + 'T10:00:00', source: 'local', ticket: 42, url: 'https://example.test/ticket/42' },
            { id: 2, title: 'Overlapping appointment', start: ymd + 'T09:30:00', end: ymd + 'T10:30:00', source: 'local' },
            { id: 'all-day', title: 'Provider all day', start: ymd, end: new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1).toLocaleDateString('en-CA'), allDay: true, source: 'google', url: 'javascript:alert(1)' }
        ];
        await page.route('**/*', async route => {
            const req = route.request(), url = new URL(req.url());
            if (url.pathname.endsWith('/js/calendar.js')) return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(path.join(root, 'public/js/calendar.js'), 'utf8') });
            if (url.pathname.endsWith('/css/calendar.css')) return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'public/css/calendar.css'), 'utf8') });
            if (url.pathname === '/qol.calendar.events') return route.fulfill({ json: events });
            if (url.pathname === '/qol.calendar.calendars') return route.fulfill({ json: [{ id: 'read', name: 'Read only', writable: false }, { id: 'write', name: 'Team', writable: true }] });
            if (url.pathname === '/qol.calendar.save') {
                assert.equal(req.headers()['x-csrf-token'], 'test-csrf'); saves.push(req.postDataJSON()); return route.fulfill({ json: { ok: true } });
            }
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
        });
        await page.goto('https://example.test/');
        await page.waitForFunction(() => document.querySelector('#qc-status').textContent.startsWith('Updated'));
        assert.equal(await page.locator('.qc-month .qc-day').count(), 42);
        assert.equal(await page.locator('.qc-event img').count(), 0, 'Event names must be rendered as text');
        await page.selectOption('#qc-view', 'week');
        await page.waitForSelector('.qc-timegrid');
        assert.equal(await page.locator('.qc-timegrid .qc-day').count(), 7);
        await page.waitForFunction(() => document.querySelectorAll('.qc-hours .qc-event').length === 2);
        const positions = await page.locator('.qc-hours .qc-event').evaluateAll(els => els.map(el => el.style.left));
        assert.notEqual(positions[0], positions[1], 'Overlapping appointments need different lanes');
        await page.selectOption('#qc-view', 'day');
        await page.waitForFunction(() => document.querySelectorAll('.qc-day').length === 1);
        await page.click('.qc-new');
        await page.fill('#qc-title', 'Ticket follow-up');
        await page.fill('#qc-start', ymd + 'T11:00'); await page.fill('#qc-end', ymd + 'T12:00');
        await page.fill('#qc-ticket', '42'); await page.click('#qc-submit');
        await page.waitForFunction(() => !document.querySelector('#qc-dialog').open);
        assert.equal(saves[0].ticket, '42'); assert.equal(saves[0].source, 'local');
        await page.selectOption('#qc-source', 'google');
        await page.waitForFunction(() => document.querySelector('#qc-calendar').options.length === 2);
        await page.click('.qc-new');
        assert.equal(await page.locator('#qc-dialog').evaluate(el => el.open), false, 'Read-only calendars must not allow creation');
        await page.selectOption('#qc-calendar', 'write'); await page.click('.qc-new');
        assert.equal(await page.locator('#qc-dialog').evaluate(el => el.open), true);
        await page.click('#qc-cancel');
        await page.selectOption('#qc-source', 'local'); await page.selectOption('#qc-view', 'month');
        await page.waitForSelector('.qc-month');
        await page.screenshot({ path: path.join(root, '.calendar-tools/calendar-preview.png'), fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        assert.equal(await page.locator('.qc-new').isVisible(), true);
        assert.deepEqual(errors, []);
        console.log('PASS: month/week/day, overlap lanes, XSS rendering, ticket scheduling, CSRF, read-only calendars, provider selection, mobile controls.');
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
