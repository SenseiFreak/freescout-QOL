// NODE_PATH must include Playwright. No live Google credentials are used.
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const script = fs.readFileSync(path.join(__dirname, '../Public/js/calendar-connections.js'), 'utf8');

(async function () {
    let browser;
    try { browser = await chromium.launch({ headless: true }); }
    catch (_) { browser = await chromium.launch({ channel: 'msedge', headless: true }); }
    try {
        const page = await browser.newPage();
        let mode = 'success', posts = 0;
        const html = '<div id="qol-calendar-connections" data-token="test-token">' +
            '<form class="qc-connection-form" method="POST" action="/connect" data-provider="Google Calendar">' +
            '<input type="hidden" name="_token" value="test-token"><button type="submit" class="qc-connect">Connect Google Calendar</button></form>' +
            '<p id="qc-connection-status" role="status"></p></div>' +
            '<script>document.addEventListener("click",function(e){e.preventDefault()});document.addEventListener("submit",function(e){e.preventDefault()});</script>' +
            '<script>' + script + '</script>';
        await page.route('**/*', async route => {
            const request = route.request(), url = new URL(request.url());
            if (url.hostname === 'accounts.google.com') return route.fulfill({ body: 'Mock Google sign-in' });
            if (url.pathname !== '/connect') return route.fulfill({ contentType: 'text/html', body: html });
            posts++;
            assert.equal(request.method(), 'POST');
            assert.equal(request.headers()['x-csrf-token'], 'test-token');
            assert.match(request.postData(), /test-token/);
            if (mode === 'missing') return route.fulfill({ status: 422, json: { message: 'Save both the client ID and client secret in Calendar setup before connecting.' } });
            if (mode === 'expired') return route.fulfill({ status: 419, body: 'Page expired' });
            if (mode === 'server') return route.fulfill({ status: 500, contentType: 'text/html', body: 'Server error' });
            if (mode === 'offline') return route.abort('failed');
            return route.fulfill({ json: { redirect_url: 'https://accounts.google.com/o/oauth2/v2/auth?state=test' } });
        });
        await page.goto('https://freescout.test/');
        await page.click('.qc-connect');
        await page.waitForURL('https://accounts.google.com/**');
        assert.equal(posts, 1, 'Click must submit exactly one authenticated POST despite delegated handlers');
        for (const [testMode, message] of [['missing', 'Save both'], ['expired', 'session may have expired'], ['server', 'HTTP 500'], ['offline', 'Failed to fetch']]) {
            mode = testMode;
            await page.goto('https://freescout.test/');
            await page.click('.qc-connect');
            await page.waitForFunction(text => document.querySelector('#qc-connection-status').textContent.includes(text), message);
            assert.equal(await page.locator('.qc-connect').isEnabled(), true);
        }
        mode = 'success';
        await page.goto('https://freescout.test/');
        await page.locator('.qc-connect').focus();
        await page.keyboard.press('Enter');
        await page.waitForURL('https://accounts.google.com/**');
        console.log('PASS: click and keyboard navigation, delegated-handler isolation, CSRF POST, missing credentials, expired session, server error, network failure.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
