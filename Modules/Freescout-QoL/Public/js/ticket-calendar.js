(function () {
    'use strict';
    function element(tag, text, className) {
        var el = document.createElement(tag); el.textContent = text;
        if (className) el.className = className;
        return el;
    }
    function safeUrl(value) {
        try { var url = new URL(value); return /^https?:$/.test(url.protocol) ? url.href : null; } catch (_) { return null; }
    }
    async function refresh() {
        var sidebar = document.getElementById('qol-ticket-events');
        if (!sidebar) return;
        var status = sidebar.querySelector('.qol-ticket-events-status');
        status.textContent = 'Loading events…';
        try {
            var response = await fetch(sidebar.dataset.url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok || response.redirected) throw new Error('Could not load linked events. Refresh the page and try again.');
            var events = await response.json(), list = sidebar.querySelector('.qol-ticket-events-list');
            list.replaceChildren();
            events.forEach(function (event) {
                var card = element('article', '', 'qol-ticket-event');
                card.appendChild(element('strong', event.title));
                card.appendChild(element('div', event.start + ' – ' + event.end));
                card.appendChild(element('small', event.timezone));
                card.appendChild(element('div', (event.imported ? 'Provider creator: ' : 'Created by: ') + event.creator, 'text-help'));
                card.appendChild(element('small', 'Created: ' + event.created_at + ' UTC', 'text-help'));
                if (event.url && safeUrl(event.url)) {
                    var line = element('div', ''), link = element('a', 'Open calendar event');
                    link.href = safeUrl(event.url); link.target = '_blank'; link.rel = 'noopener noreferrer'; line.appendChild(link); card.appendChild(line);
                }
                if (event.source !== 'local') card.appendChild(element('small', 'Recorded event times. Open the provider for subsequent changes.', 'text-help'));
                list.appendChild(card);
            });
            if (!events.length) list.appendChild(element('p', 'No linked calendar events yet.', 'text-help'));
            status.textContent = '';
        } catch (error) { status.textContent = error.message; }
    }
    document.addEventListener('click', async function (event) {
        if (event.target.closest('.qol-ticket-events-refresh')) refresh();
        var button = event.target.closest('.qol-ticket-events-import');
        if (!button || button.disabled) return;
        var status = document.querySelector('#qol-ticket-events .qol-ticket-events-status');
        button.disabled = true; status.textContent = 'Searching your default Google calendar…';
        try {
            var response = await fetch(button.dataset.url, { method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': button.dataset.token },
                body: JSON.stringify({ ticket: button.dataset.ticket }) });
            var data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not find earlier events.');
            (data.notes || []).forEach(showNote);
            await refresh(); status.textContent = data.message;
        } catch (error) { status.textContent = error.message; }
        finally { button.disabled = false; }
    });
    function showNote(note) {
        var timeline = document.getElementById('conv-layout-main');
        if (!note || !timeline || document.getElementById('qol-calendar-note-' + note.id)) return;
        // Immediate preview of the saved native note; FreeScout renders the full
        // native thread on the next page load. No ticket/editor navigation needed.
        var card = element('article', '', 'thread thread-type-note qol-calendar-note');
        card.id = 'qol-calendar-note-' + note.id;
        card.appendChild(element('strong', note.author + ' • Internal note'));
        card.appendChild(element('div', note.recorded_at, 'text-help'));
        card.appendChild(element('p', note.text));
        timeline.insertBefore(card, timeline.firstChild);
    }
    document.addEventListener('qol:calendar-created', function (event) {
        refresh();
        showNote(event.detail && event.detail.ticket_note);
    });
})();
