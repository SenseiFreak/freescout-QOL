(function () {
    'use strict';
    var root = document.getElementById('qol-calendar');
    if (!root) return;
    var source = document.getElementById('qc-source'), calendar = document.getElementById('qc-calendar');
    var view = document.getElementById('qc-view'), grid = document.getElementById('qc-grid');
    var status = document.getElementById('qc-status'), dialog = document.getElementById('qc-dialog');
    var form = document.getElementById('qc-form'), date = new Date(), events = [], generation = 0, loadingCalendars = false;
    var zone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    function field(name) { return form.elements.namedItem(name); }
    function day(d) { var v = new Date(d); v.setHours(0, 0, 0, 0); return v; }
    function add(d, n) { var v = new Date(d); v.setDate(v.getDate() + n); return v; }
    function local(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
    function pad(n) { return String(n).padStart(2, '0'); }
    function dateOf(value) { return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(value + 'T00:00:00') : new Date(value); }
    function bounds() {
        var start = day(date), end;
        if (view.value === 'month') { start.setDate(1); start = add(start, -((start.getDay() + 6) % 7)); end = add(start, 42); }
        else if (view.value === 'week') { start = add(start, -((start.getDay() + 6) % 7)); end = add(start, 7); }
        else end = add(start, 1);
        return { start: start, end: end };
    }
    async function request(url, data) {
        var options = { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.token } };
        if (data) { options.method = 'POST'; options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(data); }
        var response = await fetch(url, options), body;
        try { body = await response.json(); } catch (_) { throw new Error('Session expired or server unavailable. Reload the page.'); }
        if (!response.ok) {
            var errors = body.errors ? Object.keys(body.errors).map(function (key) { return body.errors[key].join(' '); }).join(' ') : body.message;
            throw new Error(errors || 'Calendar request failed. Please try again.');
        }
        return body;
    }
    function safeLink(value) { try { var u = new URL(value, location.href); return u.protocol === 'https:' || u.protocol === 'http:' ? u.href : ''; } catch (_) { return ''; } }
    function button(label, className, action) { var b = document.createElement('button'); b.type = 'button'; b.className = className; b.textContent = label; b.onclick = action; return b; }
    function eventButton(event) {
        var time = event.allDay ? 'All day' : dateOf(event.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        var b = button(time + '  ' + event.title, 'qc-event qc-' + event.source, function () {
            if (event.source === 'local') edit(event);
            else if (event.url && safeLink(event.url)) window.open(safeLink(event.url), '_blank', 'noopener');
        });
        b.title = event.title + (event.source === 'local' ? ' • Edit event' : ' • Open in provider to edit');
        return b;
    }
    function inDay(event, d) { return dateOf(event.start) < add(d, 1) && dateOf(event.end) > d; }
    function render() {
        var range = bounds(), month = view.value === 'month';
        grid.replaceChildren();
        document.getElementById('qc-heading').textContent = view.value === 'day'
            ? date.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
            : view.value === 'week' ? range.start.toLocaleDateString() + ' – ' + add(range.end, -1).toLocaleDateString()
                : date.toLocaleDateString([], { month: 'long', year: 'numeric' });
        grid.className = month ? 'qc-month' : 'qc-timegrid';
        var count = month ? 42 : view.value === 'week' ? 7 : 1;
        grid.style.setProperty('--qc-days', month ? 7 : count);
        if (month) {
            for (var w = 0; w < 7; w++) { var header = document.createElement('div'); header.className = 'qc-weekday'; header.textContent = add(range.start, w).toLocaleDateString([], { weekday: 'short' }); grid.appendChild(header); }
        }
        for (var i = 0; i < count; i++) {
            (function (d) {
                var cell = document.createElement('section');
                cell.className = 'qc-day' + (d.getMonth() !== date.getMonth() ? ' qc-muted' : '') + (d.getTime() === day(new Date()).getTime() ? ' qc-today' : '');
                var title = button(month ? String(d.getDate()) : d.toLocaleDateString([], { weekday: 'short', day: 'numeric' }), 'qc-day-label', function () { var at = new Date(d); at.setHours(9); edit(null, at); });
                title.setAttribute('aria-label', 'New event on ' + d.toLocaleDateString()); cell.appendChild(title);
                var dayEvents = events.filter(function (e) { return inDay(e, d); });
                if (month) dayEvents.forEach(function (event) { cell.appendChild(eventButton(event)); });
                else {
                    var all = document.createElement('div'); all.className = 'qc-allday';
                    dayEvents.filter(function (e) { return e.allDay; }).forEach(function (e) { all.appendChild(eventButton(e)); }); cell.appendChild(all);
                    var hours = document.createElement('div'); hours.className = 'qc-hours';
                    for (var h = 0; h < 24; h++) {
                        (function (hour) { var slot = button(pad(hour) + ':00', 'qc-hour', function () { var at = new Date(d); at.setHours(hour); edit(null, at); }); hours.appendChild(slot); })(h);
                    }
                    var timed = dayEvents.filter(function (e) { return !e.allDay; });
                    // Assign lanes so overlapping appointments remain individually clickable.
                    var lanes = [], placed = [];
                    timed.sort(function (a, b) { return dateOf(a.start) - dateOf(b.start); });
                    timed.forEach(function (event) {
                        var s = dateOf(event.start), e = dateOf(event.end), start = s < d ? 0 : s.getHours() * 60 + s.getMinutes();
                        var end = e >= add(d, 1) ? 1440 : e.getHours() * 60 + e.getMinutes();
                        var lane = lanes.findIndex(function (lastEnd) { return lastEnd <= start; });
                        if (lane < 0) lane = lanes.length;
                        lanes[lane] = end; placed.push({ event: event, start: start, end: end, lane: lane });
                    });
                    placed.forEach(function (p) { var b = eventButton(p.event); b.style.top = (p.start / 60 * 48) + 'px'; b.style.height = Math.max(22, (p.end - p.start) / 60 * 48) + 'px'; b.style.left = 'calc(42px + (100% - 44px) * ' + p.lane / lanes.length + ')'; b.style.width = 'calc((100% - 44px) / ' + lanes.length + ')'; hours.appendChild(b); });
                    cell.appendChild(hours);
                }
                grid.appendChild(cell);
            })(add(range.start, i));
        }
    }
    async function refresh() {
        if (loadingCalendars) return;
        var current = ++generation, range = bounds();
        status.textContent = 'Loading calendar…';
        if (source.value !== 'local' && !calendar.value) { events = []; render(); status.textContent = 'Select a connected calendar.'; return; }
        try {
            var params = new URLSearchParams({ source: source.value, calendar: calendar.value, start: range.start.toISOString(), end: range.end.toISOString() });
            var result = await request(root.dataset.events + '?' + params);
            if (current !== generation) return;
            events = result; render(); status.textContent = 'Updated ' + new Date().toLocaleTimeString() + ' • Times shown in ' + zone + (source.value === 'local' ? '' : ' • Open an event to edit in its provider');
        } catch (e) { if (current === generation) { events = []; render(); status.textContent = e.message; } }
    }
    async function changeSource() {
        var current = ++generation;
        events = []; render(); calendar.replaceChildren(); calendar.disabled = true;
        if (source.value === 'local') { loadingCalendars = false; calendar.add(new Option('Personal calendar', '')); refresh(); return; }
        loadingCalendars = true; status.textContent = 'Loading calendars…';
        try {
            var result = await request(root.dataset.calendars + '?provider=' + encodeURIComponent(source.value));
            if (current !== generation) return;
            result.forEach(function (c) { var o = new Option(c.name + (c.writable ? '' : ' (read only)'), c.id); o.dataset.writable = c.writable ? '1' : '0'; calendar.add(o); });
            calendar.disabled = false; loadingCalendars = false; refresh();
        } catch (e) { if (current === generation) { loadingCalendars = false; status.textContent = e.message; } }
    }
    function edit(event, at) {
        if (!event && source.value !== 'local' && (!calendar.value || calendar.selectedOptions[0].dataset.writable !== '1')) { status.textContent = 'Choose a writable calendar before creating an event.'; return; }
        form.reset(); field('id').value = event ? event.id : '';
        var start = event ? dateOf(event.start) : at || new Date(); start.setSeconds(0, 0);
        var end = event ? dateOf(event.end) : new Date(start.getTime() + 3600000);
        field('start').value = local(start); field('end').value = local(end); field('timezone').value = zone;
        field('title').value = event ? event.title : root.dataset.title || '';
        field('description').value = event ? event.description || '' : '';
        field('ticket').value = event ? event.ticket || '' : root.dataset.ticket || '';
        document.getElementById('qc-error').textContent = '';
        document.getElementById('qc-remove').hidden = !event;
        var link = document.getElementById('qc-ticket-link'); link.hidden = !event || !event.url;
        link.href = event && event.url ? safeLink(event.url) : '#';
        document.getElementById('qc-destination').textContent = source.value === 'local' ? 'Saved privately in FreeScout.' : 'Saved directly to ' + calendar.selectedOptions[0].text + '. Ticket links and notes will be visible to people with access to that calendar.';
        dialog.showModal();
    }
    form.onsubmit = async function (e) {
        e.preventDefault(); if (!form.reportValidity()) return;
        if (field('end').value <= field('start').value) { document.getElementById('qc-error').textContent = 'End must be after start.'; return; }
        var data = { source: source.value, calendar: calendar.value };
        ['id', 'title', 'description', 'ticket', 'start', 'end', 'timezone'].forEach(function (key) { data[key] = field(key).value || null; });
        var submit = document.getElementById('qc-submit'); submit.disabled = true;
        try { await request(root.dataset.save, data); dialog.close(); refresh(); }
        catch (error) { document.getElementById('qc-error').textContent = error.message + (source.value !== 'local' ? ' Check the provider before retrying if the request timed out.' : ''); }
        finally { submit.disabled = false; }
    };
    document.getElementById('qc-remove').onclick = async function () {
        if (!window.confirm('Delete this calendar event? The ticket will be kept.')) return;
        this.disabled = true;
        try { await request(root.dataset.delete, { id: field('id').value }); dialog.close(); refresh(); }
        catch (e) { document.getElementById('qc-error').textContent = e.message; }
        finally { this.disabled = false; }
    };
    document.getElementById('qc-cancel').onclick = function () { dialog.close(); };
    document.querySelectorAll('.qc-new').forEach(function (b) { b.onclick = function () { edit(); }; });
    function move(direction) { if (view.value === 'month') { date.setDate(1); date.setMonth(date.getMonth() + direction); } else date = add(date, direction * (view.value === 'week' ? 7 : 1)); refresh(); }
    document.getElementById('qc-prev').onclick = function () { move(-1); };
    document.getElementById('qc-next').onclick = function () { move(1); };
    document.getElementById('qc-today').onclick = function () { date = new Date(); refresh(); };
    document.getElementById('qc-refresh').onclick = refresh;
    view.onchange = refresh; source.onchange = changeSource; calendar.onchange = refresh;
    render(); refresh();
    if (root.dataset.ticket) edit();
    setInterval(function () { if (!document.hidden && !dialog.open) refresh(); }, 60000);
})();
