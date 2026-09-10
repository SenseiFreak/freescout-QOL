<?php

namespace FreescoutQOL;

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
    }
}
