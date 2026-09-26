<?php

namespace Modules\Qol\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Modules\Qol\Http\Controllers\PreferenceController;
use Modules\Qol\Http\Controllers\QolController;
use Modules\Qol\Calendar\TicketCalendar;

class QolServiceProvider extends ServiceProvider
{
    protected $moduleName = 'Qol';
    protected $moduleNameLower = 'qol';

    // Kept outside FreeScout's built-in range so it cannot collide with a core folder.
    const FOLDER_TYPE_UNRESOLVED = 90;

    public function boot()
    {
        // Built from this file's own location rather than FreeScout's module_path()/Module
        // facade helpers, which don't accept a sub-path in the bundled nwidart version and
        // would otherwise return just the module's root folder.
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', $this->moduleNameLower);
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // Only wire into FreeScout's hook system (Eventy) when it's actually available,
        // i.e. when this module is loaded inside a real FreeScout installation.
        if (class_exists(\Eventy::class)) {
            $this->registerHooks();
        }
    }

    public function register()
    {
        // Bind services or repositories here
    }

    protected function registerHooks()
    {
        \Eventy::addAction('conversation.after_customer_sidebar', function ($conversation) {
            if (!Auth::check() || !Auth::user()->can('view', $conversation)) { return; }
            echo view('qol::conversation.calendar_events', [
                'ticket' => $conversation, 'events' => TicketCalendar::rows($conversation), 'ready' => TicketCalendar::ready(),
            ])->render();
        }, 20, 1);

        // Core tracks the latest public reply separately from internal notes.
        \Eventy::addAction('conversations_table.row_class', function ($conversation) {
            if ((int) $conversation->last_reply_from === \App\Thread::PERSON_CUSTOMER) {
                echo ' qol-last-reply-customer ';
            } elseif ((int) $conversation->last_reply_from === \App\Thread::PERSON_USER) {
                echo ' qol-last-reply-agent ';
            }
        }, 20, 1);
        \Eventy::addAction('conversation.action_buttons', function () {
            echo view('qol::conversation.calendar')->render();
        });

        // --- Mailbox folder: all conversations that still need resolution ---
        // This is a normal mailbox folder entry, but it is a live view: conversations are
        // never copied or moved. It includes only active and pending conversations, which
        // deliberately excludes closed, spam and deleted conversations.
        \Eventy::addFilter('mailbox.folders', function ($folders, $mailbox) {
            $unresolved = \App\Folder::where('mailbox_id', $mailbox->id)
                ->where('type', self::FOLDER_TYPE_UNRESOLVED)
                ->whereNull('user_id')
                ->first();

            // Also makes the feature available for mailboxes created after this module was
            // installed. The migration creates it for all existing mailboxes.
            if (!$unresolved) {
                $unresolved = new \App\Folder();
                $unresolved->mailbox_id = $mailbox->id;
                $unresolved->user_id = null;
                $unresolved->type = self::FOLDER_TYPE_UNRESOLVED;
                $unresolved->active_count = 0;
                $unresolved->total_count = 0;
                $unresolved->save();
            }

            // Counters are calculated here as well as on FreeScout's regular counter
            // updates. This lets an existing installation show the correct number as soon
            // as the module update is activated.
            $count = self::unresolvedConversations($mailbox->id)->count();
            $unresolved->active_count = $count;
            $unresolved->total_count = $count;

            // Keep this working queue beside FreeScout's normal open-work folders rather
            // than placing it after the archive, spam and trash folders.
            $unassignedIndex = $folders->search(function ($folder) {
                return (int) $folder->type === \App\Folder::TYPE_UNASSIGNED;
            });
            if ($unassignedIndex !== false) {
                $folders->splice($unassignedIndex + 1, 0, [$unresolved]);
            } else {
                $folders->push($unresolved);
            }

            return $folders;
        }, 20, 2);

        \Eventy::addFilter('folder.type_name', function ($name, $folder) {
            if ((int) $folder->type === self::FOLDER_TYPE_UNRESOLVED) {
                return __('Unresolved');
            }

            return $name;
        }, 20, 2);

        \Eventy::addFilter('folder.type_icon', function ($icon, $folder) {
            if ((int) $folder->type === self::FOLDER_TYPE_UNRESOLVED) {
                return 'folder-open';
            }

            return $icon;
        }, 20, 2);

        \Eventy::addFilter('folder.conversations_query', function ($query, $folder, $userId) {
            if ((int) $folder->type !== self::FOLDER_TYPE_UNRESOLVED) {
                return $query;
            }

            return self::unresolvedConversations($folder->mailbox_id);
        }, 20, 3);

        \Eventy::addFilter('folder.update_counters', function ($handled, $folder) {
            if ((int) $folder->type !== self::FOLDER_TYPE_UNRESOLVED) {
                return $handled;
            }

            $count = self::unresolvedConversations($folder->mailbox_id)->count();
            $folder->active_count = $count;
            $folder->total_count = $count;
            $folder->save();

            return true;
        }, 20, 2);

        // --- Feature 1: keep "Arrange by / sort by" per user, so it survives page reloads ---
        // Conversation::getConvTableSorting() applies this filter for defaults *before*
        // applying whatever sorting came in the current request, so returning the user's
        // saved preference here makes it the effective default on every fresh page load.
        \Eventy::addFilter('conversations.table_sorting', function ($result) {
            $userId = Auth::id();
            if (!$userId) {
                return $result;
            }

            $saved = PreferenceController::getSavedSorting($userId);
            if ($saved) {
                $result = $saved;
            }

            return $result;
        });

        // --- Feature 2: "New" dropdown (Ticket / Contact) in the main top bar ---
        \Eventy::addAction('menu.append', function () {
            if (!Auth::check()) {
                return;
            }
            echo view('qol::mailbox.topbar')->render();
        });

        // The Manage menu is only available to administrators in FreeScout. Keep the
        // QoL entry there as a single place to find the module and its features.
        \Eventy::addAction('menu.manage.append', function () {
            if (Auth::check() && Auth::user()->isAdmin()) {
                echo '<li><a href="'.route('qol.settings').'">'.__('QoL').'</a></li>';
            }
        });

        // FreeScout exposes this slot inside its native Assignee / Status / Delete
        // bulk-action bar. The client moves the button after Delete when it is shown.
        \Eventy::addAction('bulk_actions.before_delete', function () {
            echo view('qol::bulk_merge')->render();
        });

        // Keep the subject read-only until an agent deliberately selects Edit
        // Subject from the conversation toolbar.
        \Eventy::addAction('conversation.action_buttons', function () {
            echo view('qol::conversation.edit_subject')->render();
        });

        // Load the module's CSS/JS assets on every page. Assets live in Public/ and are
        // exposed at public/modules/qol/... via the symlink FreeScout creates on activation.
        \Eventy::addAction('layout.head', function () {
            $version = @filemtime(__DIR__.'/../Public/css/qol.css') ?: '1.1.1';
            echo '<link rel="stylesheet" href="'.asset('modules/qol/css/qol.css').'?v='.$version.'">';
            if (QolController::getSettings()['gate_subject_editing']) {
                echo '<style>body .conv-subjtext{pointer-events:none}body .conv-subjtext .conv-subj-editor{pointer-events:auto}</style>';
            }
        });
        \Eventy::addAction('layout.body_bottom', function () {
            if (Auth::check()) {
                echo view('qol::conversation.calendar_panel')->render();
                echo '<link rel="stylesheet" href="'.asset('modules/qol/css/calendar-panel.css').'?v=1.3.3">';
                echo '<script src="'.asset('modules/qol/js/calendar-panel.js').'?v=1.3.4"></script>';
                echo '<script src="'.asset('modules/qol/js/ticket-calendar.js').'?v=1.3.4"></script>';
            }
            $settings = QolController::getSettings();
            echo '<script>window.QolConfig='.json_encode([
                'mergeUrl' => route('qol.conversations.merge'),
                'preferenceUrl' => route('qol.preferences.arrange_by.save'),
                'settings' => $settings,
            ]).';</script>';
            echo '<script>(function(){'
                .'var s=window.QolConfig.settings||{};'
                .'function afterSend(e){var b=e.target.closest&&e.target.closest(".btn-reply-submit");if(!b||!document.body.getAttribute("data-conversation_id"))return;var f=document.querySelector(".form-reply"),n=b.classList.contains("btn-add-note-text")||(f&&f.querySelector("[name=is_note]")&&f.querySelector("[name=is_note]").value),st=f&&f.querySelector("[name=status]"),v=!n&&st&&String(st.value)==="3"?"2":"1";Array.prototype.forEach.call(document.querySelectorAll("[name=after_send]"),function(i){i.value=v});}'
                .'if(s.stay_on_ticket!==false){document.addEventListener("pointerdown",afterSend,true);document.addEventListener("mousedown",afterSend,true);document.addEventListener("click",afterSend,true)}'
                .'if(s.gate_subject_editing!==false){document.addEventListener("click",function(e){var b=e.target.closest&&e.target.closest(".qol-edit-subject");if(b){e.preventDefault();e.stopImmediatePropagation();var t=document.querySelector(".conv-subjtext"),i=document.getElementById("conv-subj-value");if(t)t.classList.add("conv-subj-editing");if(i){i.focus();i.select()}return}var t=e.target.closest&&e.target.closest(".conv-subjtext");if(t){e.preventDefault();e.stopImmediatePropagation()}},true)}'
                .'})();</script>';
            $version = @filemtime(__DIR__.'/../Public/js/qol.js') ?: '1.1.1';
            echo '<script src="'.asset('modules/qol/js/qol.js').'?v='.$version.'"></script>';
        });

        // --- Feature 3: use the email's own Date header instead of the import/fetch time ---
        // FreeScout already supports this natively via config('app.use_mail_date_on_fetching'),
        // it is just off by default. Force it on so "Waiting Since" reflects when the
        // customer actually sent the email, not when it was fetched/imported.
        config(['app.use_mail_date_on_fetching' => true]);

        // --- Feature 4: Freshdesk-style merge ---
        // When two conversations are merged, keep the merged-away customer on the primary
        // conversation as a CC so future replies still reach them.
        // Eventy only forwards 1 argument to listeners by default, so this must be
        // registered with $arguments = 3 to receive $conversation, $second_conversation, $user.
        \Eventy::addAction('conversation.merged', function ($conversation = null, $second_conversation = null, $user = null) {
            // A CC update is an enhancement, not a reason for FreeScout's core
            // merge to fail. Older Eventy versions can also pass fewer arguments.
            try {
                if (!$conversation || !$second_conversation || !$second_conversation->customer_email
                    || $second_conversation->customer_email == $conversation->customer_email
                ) {
                    return;
                }

                $cc = $conversation->getCcArray();
                if (!in_array($second_conversation->customer_email, $cc)) {
                    $cc[] = $second_conversation->customer_email;
                    $conversation->setCc($cc);
                    $conversation->save();
                }
            } catch (\Throwable $exception) {
                \Log::warning('QoL could not add the merged customer as a CC', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }, 20, 3);
    }

    /**
     * Shared query for the Unresolved folder and its counter.
     */
    protected static function unresolvedConversations($mailboxId)
    {
        return \App\Conversation::where('mailbox_id', $mailboxId)
            ->where('state', \App\Conversation::STATE_PUBLISHED)
            ->whereIn('status', [
                \App\Conversation::STATUS_ACTIVE,
                \App\Conversation::STATUS_PENDING,
            ]);
    }
}
