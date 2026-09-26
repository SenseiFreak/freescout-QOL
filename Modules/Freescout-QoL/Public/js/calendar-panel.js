(function () {
    'use strict';
    var panel = document.getElementById('qol-calendar-panel');
    if (!panel || !panel.showModal || !window.fetch) return;
    var form = document.getElementById('qcp-form'), fields = document.getElementById('qcp-fields');
    var status = document.getElementById('qcp-status'), save = document.getElementById('qcp-save');
    var calendars = document.getElementById('qcp-calendar'), sequence = 0, saving = false;
    function field(name) { return form.elements.namedItem(name); }
    function message(text, style) { status.textContent = text; status.className = style || ''; }
    function local(date) {
        function pad(n) { return String(n).padStart(2, '0'); }
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }
    async function request(url, data) {
        var controller = new AbortController(), timer = setTimeout(function () { controller.abort(); }, 60000);
        try {
            var options = { credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': panel.dataset.token } };
            if (data) { options.method = 'POST'; options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(data); }
            var response = await fetch(url, options);
            if (response.redirected || response.status === 419 || response.status === 401) throw new Error('Your session expired. Sign in again and retry.');
            var body;
            try { body = await response.json(); } catch (_) { throw new Error('FreeScout returned an unexpected response. Check the server logs.'); }
            if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat().join(' ') : body.message || 'Could not save the calendar event.');
            return body;
        } finally { clearTimeout(timer); }
    }
    function destinationChanged() {
        var selected = calendars.selectedOptions[0];
        save.disabled = fields.disabled || !selected || selected.disabled;
        document.getElementById('qcp-sharing').textContent = !selected || selected.disabled ? 'Choose an available writable calendar.'
            : JSON.parse(selected.value).source === 'local' ? 'Linked event details are visible to agents who can view this ticket.'
                : 'The event details and ticket link will be visible to people with access to this calendar. A record is also saved on this ticket.';
    }
    async function open(ticket) {
        var current = ++sequence;
        form.reset(); calendars.replaceChildren(); fields.disabled = true; save.disabled = true;
        document.getElementById('qcp-cancel').textContent = 'Cancel';
        message('Loading ticket and calendars…');
        panel.showModal();
        try {
            var data = await request(panel.dataset.context + '?ticket=' + encodeURIComponent(ticket));
            if (current !== sequence || !panel.open) return;
            field('ticket').value = data.ticket; field('title').value = data.title;
            var start = new Date(); start.setSeconds(0, 0);
            field('start').value = local(start); field('end').value = local(new Date(start.getTime() + 3600000));
            field('timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
            data.calendars.options.forEach(function (item) {
                var value = JSON.stringify({ source: item.source, calendar: item.calendar });
                var option = new Option(item.name, value);
                option.disabled = item.writable === false;
                option.selected = item.source === data.calendars.saved.source && item.calendar === data.calendars.saved.calendar;
                calendars.add(option);
            });
            fields.disabled = false; destinationChanged();
            message(data.calendars.errors.join('\n'), data.calendars.errors.length ? 'qcp-error' : '');
            field('title').focus();
        } catch (error) {
            if (current === sequence) message(error.name === 'AbortError' ? 'Loading timed out. Close this panel and try again.' : error.message, 'qcp-error');
        }
    }
    function close() { if (!saving) { sequence++; panel.close(); } }
    document.getElementById('qcp-close').onclick = close;
    document.getElementById('qcp-cancel').onclick = close;
    panel.addEventListener('cancel', function (event) { if (saving) event.preventDefault(); else sequence++; });
    calendars.onchange = destinationChanged;
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.qol-add-calendar');
        if (!trigger) return;
        var ticket = document.body.getAttribute('data-conversation_id');
        if (!ticket || !/^\d+$/.test(ticket)) return; // Preserve full-calendar fallback.
        event.preventDefault(); event.stopImmediatePropagation();
        if (!panel.open) open(ticket);
    }, true);
    form.addEventListener('submit', async function (event) {
        event.preventDefault(); event.stopImmediatePropagation();
        if (saving || save.disabled || !form.reportValidity()) return;
        if (field('end').value <= field('start').value) { message('End must be after start.', 'qcp-error'); return; }
        var data = JSON.parse(calendars.value);
        ['ticket', 'title', 'start', 'end', 'timezone', 'description'].forEach(function (name) { data[name] = field(name).value; });
        saving = true; fields.disabled = true; save.disabled = true;
        message('Creating calendar event…');
        try {
            var result = await request(panel.dataset.save, data);
            message(result.warning || 'Event created and linked to this ticket.', result.warning ? 'qcp-error' : 'qcp-success');
            document.dispatchEvent(new CustomEvent('qol:calendar-created', { detail: result }));
            document.getElementById('qcp-cancel').textContent = 'Done';
            // Keep submission disabled after success to prevent duplicate events.
        } catch (error) {
            fields.disabled = false; destinationChanged();
            message((error.name === 'AbortError' ? 'The request timed out.' : error.message) + (data.source !== 'local' ? ' Check your calendar before retrying if the request may have reached the provider.' : ''), 'qcp-error');
        } finally { saving = false; }
    }, true);
})();
