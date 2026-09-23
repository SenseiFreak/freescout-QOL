(function () {
    function csrfToken() {
        return (window.Laravel && window.Laravel.csrfToken)
            || (document.querySelector('meta[name=csrf-token]') && document.querySelector('meta[name=csrf-token]').getAttribute('content'));
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify(body),
            credentials: 'same-origin',
            keepalive: true
        });
    }

    function preferenceUrl() {
        return (window.QolConfig || {}).preferenceUrl || '/qol/preferences/arrange-by';
    }

    function saveSorting(sortBy, order) {
        if (!sortBy || !order) return;

        var value = JSON.stringify({ sort_by: sortBy, order: order });
        try { window.localStorage.setItem(sortingStorageKey(), value); } catch (ignore) {}

        // Beacon is dispatched before FreeScout starts its AJAX reload, so navigation
        // to another folder cannot cancel the preference request.
        var data = '_token=' + encodeURIComponent(csrfToken() || '')
            + '&sort_by=' + encodeURIComponent(sortBy)
            + '&order=' + encodeURIComponent(order);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(preferenceUrl(), new Blob([data], {
                type: 'application/x-www-form-urlencoded;charset=UTF-8'
            }));
        } else {
            postJson(preferenceUrl(), { sort_by: sortBy, order: order });
        }
    }

    function sortingStorageKey() {
        return 'qol.mailbox.sorting.' + (document.body.getAttribute('data-auth_user_id') || 'guest');
    }

    // --- Feature 2: "New" dropdown open/close (native FreeScout dropdown-menu markup
    // already handles Bootstrap's data-toggle="dropdown" behaviour; this just guards
    // against double-binding and closes the menu on an outside click). ---
    function initNewMenu() {
        document.addEventListener('click', function (e) {
            var menu = document.getElementById('qol-new-menu');
            var button = document.getElementById('qol-new-button');
            if (!menu || !button) return;
            if (menu.classList.contains('open') && !menu.contains(e.target) && !button.contains(e.target)) {
                menu.classList.remove('open');
            }
        });
    }

    // --- Feature 1: persist "Arrange by" / column sorting per user ---
    function initSortPersistence() {
        // This browser copy is a fallback for a refresh that happens immediately
        // after clicking a column, before the database write can finish.
        try {
            var saved = JSON.parse(window.localStorage.getItem(sortingStorageKey()) || 'null');
            var table = document.querySelector('.table-conversations');
            if (saved && table && saved.sort_by && saved.order
                && table.getAttribute('data-sorting_sort_by') !== saved.sort_by) {
                table.setAttribute('data-sorting_sort_by', saved.sort_by);
                table.setAttribute('data-sorting_order', saved.order);
                if (typeof window.loadConversations === 'function') {
                    window.setTimeout(function () { window.loadConversations('', '', true); }, 0);
                }
            }
        } catch (ignore) {}

        // Any element using the generic data-qol-arrange-by convention.
        document.querySelectorAll('[data-qol-arrange-by]').forEach(function (el) {
            el.addEventListener('change', function () {
                postJson(preferenceUrl(), { arrange_by: el.value });
            });
        });

        function persistNextSort(e) {
            var sortEl = e.target.closest && e.target.closest('.conv-col-sort');
            if (!sortEl) return;

            var sortBy = sortEl.getAttribute('data-sort-by');
            var currentOrder = sortEl.getAttribute('data-order');
            var newOrder = currentOrder === 'asc' ? 'desc' : 'asc';

            if (!sortBy) return;
            saveSorting(sortBy, newOrder);
        }

        // Save on pointer-down, before FreeScout's click handler begins the list reload.
        // A click listener also covers keyboard activation of a sortable column.
        document.addEventListener('pointerdown', function (e) {
            var sortEl = e.target.closest && e.target.closest('.conv-col-sort');
            if (sortEl) sortEl.setAttribute('data-qol-sort-pending', '1');
            persistNextSort(e);
        }, true);
        document.addEventListener('click', function (e) {
            var sortEl = e.target.closest && e.target.closest('.conv-col-sort');
            if (sortEl && sortEl.getAttribute('data-qol-sort-pending') === '1') {
                sortEl.removeAttribute('data-qol-sort-pending');
                return;
            }
            persistNextSort(e);
        }, true);
    }

    // --- Feature 4: merge selected conversations from FreeScout's bulk action bar ---
    function initBulkMerge() {
        var button = document.querySelector('.qol-bulk-merge');
        var modal = document.getElementById('qol-bulk-merge-modal');
        if (!button || !modal || !window.jQuery) return;

        var $ = window.jQuery;
        $(button).insertAfter($('.conv-delete:first'));
        $(modal).appendTo(document.body);

        $(button).on('click', function (e) {
            var selected = [];
            $('.conv-checkbox:checked').each(function () {
                var row = $(this).closest('.conv-row');
                selected.push({
                    id: String(this.value),
                    label: '#' + $.trim(row.find('.conv-number').text()) + ' — ' + $.trim(row.find('.conv-subject').text())
                });
            });
            if (selected.length < 2) return;

            var select = document.getElementById('qol-bulk-merge-primary');
            select.innerHTML = '';
            selected.forEach(function (conversation) {
                var option = document.createElement('option');
                option.value = conversation.id;
                option.textContent = conversation.label;
                select.appendChild(option);
            });
            $(modal).data('qol-conversations', selected);
            showModalDialog('#qol-bulk-merge-modal', {
                on_show: function (dialog) {
                    dialog.find('.qol-bulk-merge-confirm').off('click.qol').on('click.qol', function (event) {
                        event.preventDefault();
                        var confirmButton = $(this).button('loading');
                        postJson((window.QolConfig || {}).mergeUrl || '/qol/conversations/merge', {
                            primary_conversation_id: select.value,
                            conversation_ids: selected.map(function (conversation) { return conversation.id; })
                        }).then(function (response) {
                            if (!response.ok) throw new Error();
                            window.location.reload();
                        }).catch(function () {
                            confirmButton.button('reset');
                            alert('Unable to merge the selected conversations.');
                        });
                    });
                }
            });
        });
    }

    // --- Feature 5: highlight customer vs agent replies under the name/email ---
    function labelThreadReplies(root) {
        (root || document).querySelectorAll('.thread').forEach(function (thread) {
            if (thread.querySelector('.qol-reply-badge')) return; // already labeled

            var isCustomer = thread.classList.contains('thread-type-customer');
            var isAgent = thread.classList.contains('thread-type-message');

            if (!isCustomer && !isAgent) return; // notes/lineitems are left as-is

            var anchor = thread.querySelector('.thread-recipients') || thread.querySelector('.thread-person');
            if (!anchor) return;

            var badge = document.createElement('div');
            badge.className = 'qol-reply-badge ' + (isCustomer ? 'qol-reply-customer' : 'qol-reply-agent');
            badge.textContent = isCustomer ? 'Customer reply' : 'Agent reply';

            anchor.parentNode.insertBefore(badge, anchor.nextSibling);
        });
    }

    function initReplyBadges() {
        labelThreadReplies(document);

        // Threads can be loaded/edited dynamically (new replies, ajax loads); watch for it.
        var container = document.getElementById('conv-layout-main') || document.body;
        if (!window.MutationObserver) return;

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.addedNodes && m.addedNodes.length) {
                    labelThreadReplies(container);
                }
            });
        });
        observer.observe(container, { childList: true, subtree: true });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initNewMenu();
        initSortPersistence();
        initBulkMerge();
        initReplyBadges();
    });
})();

