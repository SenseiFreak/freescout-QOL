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

    function setting(name) {
        var settings = (window.QolConfig || {}).settings || {};
        return settings[name] !== false;
    }

    function saveSorting(sortBy, order) {
        if (!sortBy || !order) return;

        var value = JSON.stringify({ sort_by: sortBy, order: order });
        try { window.localStorage.setItem(sortingStorageKey(), value); } catch (ignore) {}

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
                        var mergeIds = selected
                            .map(function (conversation) { return conversation.id; })
                            .filter(function (id) { return id !== select.value; });

                        // Use FreeScout's native merge action. It already handles
                        // permissions, history entries, attachments and counters.
                        fsAjax({
                            action: 'conversation_merge',
                            conversation_id: select.value,
                            merge_conversation_id: mergeIds
                        }, laroute.route('conversations.ajax'), function (response) {
                            if (isAjaxSuccess(response)) {
                                window.location.reload();
                            } else {
                                confirmButton.button('reset');
                                showAjaxError(response);
                            }
                        }, true, function (jqXHR, textStatus, errorThrown) {
                            confirmButton.button('reset');
                            var message = 'Unable to merge the selected conversations.';
                            if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.msg) {
                                message = jqXHR.responseJSON.msg;
                            } else if (jqXHR && jqXHR.status === 419) {
                                message = 'Your FreeScout session expired. Refresh the page and try again.';
                            } else if (jqXHR && jqXHR.status) {
                                message += ' Server response: '+jqXHR.status+'.';
                            }
                            showFloatingAlert('error', message);
                        });
                    });
                }
            });
        });
    }

    // --- Keep replies and notes on the current ticket; closing advances to next ---
    function initStayOnTicket() {
        if (!setting('stay_on_ticket')) return;

        function setRedirectForSend(event) {
            var button = event.target.closest && event.target.closest('.btn-reply-submit');
            if (!button || !document.body.getAttribute('data-conversation_id')) return;

            var form = document.querySelector('.form-reply');
            var isNote = button.classList.contains('btn-add-note-text')
                || (form && form.querySelector('[name="is_note"]') && form.querySelector('[name="is_note"]').value);
            var status = form && form.querySelector('[name="status"]');

            // FreeScout: 1 = stay, 2 = next active conversation, 3 = closed.
            var afterSend = !isNote && status && String(status.value) === '3' ? '2' : '1';

            // FreeScout copies the toolbar into the Summernote editor, leaving
            // multiple after_send inputs in the DOM. Update every copy so the
            // value that form.serialize() submits is always the intended one.
            Array.prototype.forEach.call(document.querySelectorAll('[name="after_send"]'), function (input) {
                input.value = afterSend;
            });
        }

        // Run before FreeScout's own click handler starts serializing the form.
        document.addEventListener('pointerdown', setRedirectForSend, true);
        document.addEventListener('mousedown', setRedirectForSend, true);
        document.addEventListener('click', setRedirectForSend, true);
    }

    // --- Subject editing requires the dedicated toolbar action ---
    function initSubjectEditGate() {
        if (!setting('gate_subject_editing')) {
            Array.prototype.forEach.call(document.querySelectorAll('.qol-edit-subject'), function (button) {
                button.style.display = 'none';
            });
            return;
        }

        document.body.classList.add('qol-subject-gated');
        document.addEventListener('click', function (event) {
            var editButton = event.target.closest && event.target.closest('.qol-edit-subject');
            if (editButton) {
                event.preventDefault();
                event.stopImmediatePropagation();
                var subject = document.querySelector('.conv-subjtext');
                if (!subject) return;
                subject.classList.add('conv-subj-editing');
                var input = document.getElementById('conv-subj-value');
                if (input) {
                    input.focus();
                    input.select();
                }
                return;
            }

            var title = event.target.closest && event.target.closest('.conv-subjtext');
            if (!title) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
    }

    // --- Drop files directly onto the FreeScout editor to reuse its uploader ---
    function initDragDropAttachments() {
        if (!setting('drag_drop_attachments')) return;

        function editorFromEvent(event) {
            return event.target.closest && event.target.closest('.note-editable');
        }

        document.addEventListener('dragover', function (event) {
            var editor = editorFromEvent(event);
            if (!editor || !event.dataTransfer || !event.dataTransfer.files.length) return;
            event.preventDefault();
            editor.classList.add('qol-drop-target');
        });
        document.addEventListener('dragleave', function (event) {
            var editor = editorFromEvent(event);
            if (editor) editor.classList.remove('qol-drop-target');
        });
        document.addEventListener('drop', function (event) {
            var editor = editorFromEvent(event);
            if (!editor || !event.dataTransfer || !event.dataTransfer.files.length) return;
            event.preventDefault();
            editor.classList.remove('qol-drop-target');
            if (typeof window.editorSendFile !== 'function') return;
            Array.prototype.forEach.call(event.dataTransfer.files, function (file) {
                window.editorSendFile(file, undefined, true);
            });
        });
    }

    // --- Latest public reply badge below the customer email in mailbox rows ---
    function labelThreadReplies(root) {
        (root || document).querySelectorAll('.thread .qol-reply-badge').forEach(function (badge) { badge.remove(); });
        (root || document).querySelectorAll('.table-conversations .conv-row').forEach(function (row) {
            var isCustomer = row.classList.contains('qol-last-reply-customer');
            var isAgent = row.classList.contains('qol-last-reply-agent');
            var anchor = row.querySelector('.conv-customer .conv-email');
            var badge = row.querySelector('.qol-reply-badge');
            if (!anchor || (!isCustomer && !isAgent)) { if (badge) badge.remove(); return; }
            var label = isCustomer ? 'Customer reply' : 'Agent reply';
            if (badge && badge.textContent === label) return;
            if (!badge) badge = document.createElement('span');
            badge.className = 'qol-reply-badge ' + (isCustomer ? 'qol-reply-customer' : 'qol-reply-agent');
            badge.textContent = label;
            anchor.parentNode.insertBefore(badge, anchor.nextSibling);
        });
    }

    function initReplyBadges() {
        labelThreadReplies(document);

        // Mailbox sorting/pagination replaces rows without a full page load.
        var container = document.body;
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
        initStayOnTicket();
        initSubjectEditGate();
        initDragDropAttachments();
        initReplyBadges();
    });
})();
