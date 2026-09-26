<?php

namespace FreescoutQOL;

use FreescoutQOL\Controllers\PreferenceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        // Bind services or repositories here
    }

    public function boot()
    {
        // Load routes
        if (file_exists(__DIR__.'/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'freescout-qol');

        // Publish assets and migrations if running in an application
        $this->publishes([__DIR__.'/../public' => public_path('vendor/freescout-qol')], 'public');
        $this->publishes([__DIR__.'/../migrations' => database_path('migrations')], 'migrations');

        // Only wire into FreeScout's hook system (Eventy) when it's actually available,
        // i.e. when this module is loaded inside a real FreeScout installation.
        if (class_exists(\Eventy::class)) {
            $this->registerHooks();
        }
    }

    protected function registerHooks()
    {
        \Eventy::addAction('conversation.action_buttons', function () {
            echo view('freescout-qol::conversation.calendar')->render();
        });

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
            echo view('freescout-qol::mailbox.topbar')->render();
        });

        \Eventy::addAction('menu.manage.append', function () {
            if (Auth::check() && Auth::user()->isAdmin()) {
                echo '<li><a href="'.route('qol.settings').'">'.__('QoL').'</a></li>';
            }
        });

        \Eventy::addAction('bulk_actions.before_delete', function () {
            echo view('freescout-qol::bulk_merge')->render();
        });

        // Load the module's CSS/JS assets on every page (after publishing to public/vendor/freescout-qol).
        \Eventy::addAction('layout.head', function () {
            echo '<link rel="stylesheet" href="'.asset('vendor/freescout-qol/css/qol.css').'">';
        });
        \Eventy::addAction('layout.body_bottom', function () {
            echo '<script>window.QolConfig='.json_encode([
                'mergeUrl' => route('qol.conversations.merge'),
                'preferenceUrl' => route('qol.preferences.arrange_by.save'),
            ]).';</script>';
            echo '<script src="'.asset('vendor/freescout-qol/js/qol.js').'"></script>';
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
        \Eventy::addAction('conversation.merged', function ($conversation, $second_conversation, $user) {
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
        }, 20, 3);
    }
}
