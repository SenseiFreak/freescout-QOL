(function () {
    'use strict';
    var root = document.getElementById('qol-calendar-connections') || document.getElementById('qol-calendar');
    if (!root || !window.fetch) return; // Retain ordinary CSRF-protected POST fallback.
    var status = document.getElementById('qc-connection-status');
    var busy = false;

    async function connect(form) {
        if (busy) return;
        busy = true;
        var buttons = root.querySelectorAll('.qc-connect');
        buttons.forEach(function (button) { button.disabled = true; });
        status.textContent = 'Contacting FreeScout for ' + form.dataset.provider + '…';
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, 25000);
        try {
            var response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.token },
                body: new FormData(form)
            });
            if (response.status === 419 || response.status === 401 || response.redirected) {
                throw new Error('Your FreeScout session may have expired. Sign in again, then retry.');
            }
            var data;
            try { data = await response.json(); }
            catch (_) { throw new Error('FreeScout returned an unexpected response (HTTP ' + response.status + '). Check the server logs.'); }
            if (!response.ok) throw new Error(data.message || 'FreeScout could not start the connection (HTTP ' + response.status + ').');
            if (!data.redirect_url) throw new Error('The server is running an older calendar controller. Upload the updated module files and clear cached views.');
            var target = new URL(data.redirect_url, location.href);
            var providerHost = target.hostname === 'accounts.google.com' || target.hostname === 'login.microsoftonline.com';
            if (target.origin !== location.origin && !(target.protocol === 'https:' && providerHost)) {
                throw new Error('FreeScout returned an unexpected sign-in address.');
            }
            status.textContent = 'Opening ' + form.dataset.provider + '…';
            window.location.assign(target.href);
        } catch (error) {
            status.textContent = error.name === 'AbortError' ? 'FreeScout did not respond within 25 seconds. Check the server logs and retry.' : error.message;
        } finally {
            clearTimeout(timer);
            busy = false;
            buttons.forEach(function (button) { button.disabled = false; });
        }
    }

    // Handle these small forms independently of the event editor and global
    // delegated form/button handlers installed by FreeScout or other modules.
    root.addEventListener('click', function (event) {
        var button = event.target.closest('.qc-connect');
        if (!button || !button.form || !button.form.matches('.qc-connection-form')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        connect(button.form);
    }, true);
    root.addEventListener('submit', function (event) {
        if (!event.target.matches('.qc-connection-form')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        connect(event.target);
    }, true);
})();
