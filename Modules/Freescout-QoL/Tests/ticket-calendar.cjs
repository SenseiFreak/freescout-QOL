const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const base = path.resolve(__dirname, '..');
const js = ['qol.js', 'ticket-calendar.js'].map(f => fs.readFileSync(path.join(base, 'Public/js', f), 'utf8')).join('\n');
const css = fs.readFileSync(path.join(base, 'Public/css/qol.css'), 'utf8');
const html = '<!doctype html><meta charset="utf-8"><style>body{font:14px Arial;color:#243746;padding:24px}td{padding:14px;border-bottom:1px solid #dde3ea}.conv-email{display:block;color:#637487}#conv-layout-main{width:70%;float:left}#conv-layout-customer{width:26%;float:right}a{color:#0078d4}small{font-size:11px}.text-help{color:#637487}' + css + '</style>' +
    '<table class="table-conversations"><tbody><tr class="conv-row qol-last-reply-customer"><td class="conv-customer"><a>Customer One<span class="conv-email">one@example.test</span></a></td></tr><tr class="conv-row qol-last-reply-agent"><td class="conv-customer"><a>Customer Two<span class="conv-email">two@example.test</span></a></td></tr><tr class="conv-row"><td class="conv-customer"><a>No reply<span class="conv-email">draft@example.test</span></a></td></tr></tbody></table>' +
    '<div id="conv-layout-main"><div class="thread thread-type-customer"><span class="qol-reply-badge">Old customer badge</span><p>Customer message</p></div></div>' +
    '<div id="conv-layout-customer"><h3>Customer One</h3><p>one@example.test</p><section id="qol-ticket-events" data-url="/events"><h3>Calendar events</h3><div class="qol-ticket-events-list"></div><button class="qol-ticket-events-refresh">Refresh</button><button class="qol-ticket-events-import" data-url="/import" data-ticket="42" data-token="csrf-test">Find earlier Google events</button><p class="qol-ticket-events-status"></p></section></div>' +
    '<script>window.QolConfig={settings:{stay_on_ticket:false,gate_subject_editing:false,drag_drop_attachments:false}};</script><script>' + js + '</script>';
(async function () {
    let browser;
    try { browser = await chromium.launch({ headless: true }); }
    catch (_) { browser = await chromium.launch({ channel: 'msedge', headless: true }); }
    try {
        const page = await browser.newPage({ viewport: { width: 1300, height: 800 } });
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        let fail = false;
        const note = { id: 77, author: 'Kyle', recorded_at: '2026-09-26 14:00 Africa/Johannesburg', text: 'Calendar event created: Laptop repair\nWhen: 2026-09-28 09:00 – 2026-09-28 10:00\nCreated by: Kyle' };
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/events') return route.fulfill(fail ? { status: 403, json: {} } : { json: [{ id: 1, title: 'Laptop repair <img src=x onerror=alert(1)>', start: '2026-09-28 09:00', end: '2026-09-28 10:00', timezone: 'Africa/Johannesburg', creator: 'Kyle', created_at: '2026-09-26 12:00:00', source: 'google', url: 'javascript:alert(1)' }] });
            if (url.pathname === '/import') {
                assert.equal(route.request().method(), 'POST'); assert.equal(route.request().headers()['x-csrf-token'], 'csrf-test');
                return route.fulfill({ json: { notes: [note], message: 'Linked 1 earlier Google events.' } });
            }
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
        });
        await page.goto('https://freescout.test/ticket/42');
        assert.equal(await page.locator('.thread .qol-reply-badge').count(), 0);
        assert.equal(await page.locator('.table-conversations .qol-reply-badge').count(), 2);
        assert.equal(await page.locator('.conv-email + .qol-reply-customer').textContent(), 'Customer reply');
        assert.equal(await page.locator('.conv-email + .qol-reply-agent').textContent(), 'Agent reply');
        await page.evaluate(() => { const row = document.querySelector('.conv-row').cloneNode(true); row.querySelector('.qol-reply-badge').remove(); document.querySelector('tbody').appendChild(row); });
        await page.waitForFunction(() => document.querySelectorAll('.table-conversations .qol-reply-badge').length === 3);
        await page.evaluate(note => document.dispatchEvent(new CustomEvent('qol:calendar-created', { detail: { ticket_note: note } })), note);
        await page.waitForSelector('.qol-ticket-event');
        assert.equal(await page.locator('.qol-calendar-note').count(), 1);
        assert.equal(await page.locator('.qol-ticket-event img, .qol-ticket-event a').count(), 0, 'Untrusted titles/URLs must not execute');
        await page.evaluate(note => document.dispatchEvent(new CustomEvent('qol:calendar-created', { detail: { ticket_note: note } })), note);
        assert.equal(await page.locator('.qol-calendar-note').count(), 1, 'No duplicate note previews');
        await page.click('.qol-ticket-events-import');
        await page.waitForFunction(() => document.querySelector('.qol-ticket-events-status').textContent.includes('Linked 1'));
        assert.equal(await page.locator('.qol-calendar-note').count(), 1);
        fail = true; await page.click('.qol-ticket-events-refresh');
        await page.waitForFunction(() => document.querySelector('.qol-ticket-events-status').textContent.includes('Could not load'));
        assert.equal(await page.locator('.qol-ticket-event').count(), 1, 'Keep existing cards when refresh fails');
        if (process.env.QOL_PREVIEW) await page.screenshot({ path: process.env.QOL_PREVIEW, fullPage: true });
        assert.deepEqual(errors, []);
        console.log('PASS: mailbox badge placement/AJAX rows, no thread badges, sidebar refresh, note preview/deduplication, legacy import POST, safe text/URLs, refresh failure.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
