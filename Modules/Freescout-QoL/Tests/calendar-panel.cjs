// Run with Playwright on NODE_PATH. The ticket and provider APIs are mocked.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const base = path.resolve(__dirname, '..');
let html = fs.readFileSync(path.join(base, 'Resources/views/conversation/calendar_panel.blade.php'), 'utf8')
    .replace(/\{\{\s*__\('([^']*)'\)\s*\}\}/g, '$1')
    .replace(/\{\{\s*route\('([^']*)'\)\s*\}\}/g, (_, name) => '/' + name)
    .replace(/\{\{\s*csrf_token\(\)\s*\}\}/g, 'csrf-test');
html = '<!doctype html><meta charset="utf-8"><body data-conversation_id="42"><a class="qol-add-calendar" href="/calendar">Add to calendar</a>' + html + '<script>' + fs.readFileSync(path.join(base, 'Public/js/calendar-panel.js'), 'utf8') + '</script>';
(async function () {
    let browser;
    try { browser = await chromium.launch({ headless: true }); }
    catch (_) { browser = await chromium.launch({ channel: 'msedge', headless: true }); }
    try {
        const page = await browser.newPage();
        let mode = 'success', saved = [], errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.route('**/*', async route => {
            const req = route.request(), url = new URL(req.url());
            if (url.pathname === '/qol.calendar.ticket-event') {
                assert.equal(url.searchParams.get('ticket'), '42');
                if (mode === 'forbidden') return route.fulfill({ status: 403, json: { message: 'Ticket access denied.' } });
                return route.fulfill({ json: { ticket: 42, title: '#123 Ticket title', calendars: {
                    saved: { source: 'google', calendar: 'chosen' }, errors: [], options: [
                        { source: 'local', calendar: '', name: 'FreeScout' },
                        { source: 'google', calendar: 'chosen', name: 'My Google calendar', writable: mode !== 'unavailable' }
                    ]
                } } });
            }
            if (url.pathname === '/qol.calendar.save') {
                assert.equal(req.headers()['x-csrf-token'], 'csrf-test');
                saved.push(req.postDataJSON());
                if (mode === 'validation') return route.fulfill({ status: 422, json: { errors: { timezone: ['Invalid timezone.'] } } });
                return route.fulfill({ json: { ok: true } });
            }
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
        });
        await page.goto('https://ticket.test/conversation/42');
        await page.click('.qol-add-calendar');
        await page.waitForFunction(() => !document.querySelector('#qcp-fields').disabled);
        assert.equal(await page.inputValue('#qcp-title'), '#123 Ticket title');
        assert.equal(JSON.parse(await page.inputValue('#qcp-calendar')).calendar, 'chosen');
        await page.fill('#qcp-description', 'Discuss next steps and meeting location.');
        await page.click('#qcp-save');
        await page.waitForFunction(() => document.querySelector('#qcp-status').textContent.includes('Event created'));
        assert.equal(page.url(), 'https://ticket.test/conversation/42');
        assert.equal(saved.length, 1); assert.equal(saved[0].ticket, '42'); assert.equal(saved[0].source, 'google');
        assert.equal(saved[0].description, 'Discuss next steps and meeting location.');
        assert.equal(await page.locator('#qcp-save').isDisabled(), true);
        await page.click('#qcp-cancel');
        assert.equal(await page.locator('#qol-calendar-panel').evaluate(el => el.open), false);
        mode = 'unavailable'; await page.click('.qol-add-calendar');
        await page.waitForFunction(() => !document.querySelector('#qcp-fields').disabled);
        assert.equal(await page.locator('#qcp-save').isDisabled(), true);
        await page.selectOption('#qcp-calendar', JSON.stringify({ source: 'local', calendar: '' }));
        assert.equal(await page.locator('#qcp-save').isEnabled(), true);
        await page.keyboard.press('Escape');
        mode = 'validation'; await page.click('.qol-add-calendar');
        await page.waitForFunction(() => !document.querySelector('#qcp-fields').disabled);
        await page.click('#qcp-save');
        await page.waitForFunction(() => document.querySelector('#qcp-status').textContent.includes('Invalid timezone'));
        assert.equal(await page.locator('#qcp-save').isEnabled(), true);
        await page.click('#qcp-close');
        mode = 'forbidden'; await page.click('.qol-add-calendar');
        await page.waitForFunction(() => document.querySelector('#qcp-status').textContent.includes('access denied'));
        assert.equal(await page.locator('#qcp-save').isDisabled(), true);
        assert.deepEqual(errors, []);
        console.log('PASS: drawer opening, ticket/default prefill, event details and CSRF payload, stays on ticket, duplicate prevention, unavailable calendar, validation errors, denied ticket, close/Escape.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
