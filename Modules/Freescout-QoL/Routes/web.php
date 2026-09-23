<?php

use Illuminate\Support\Facades\Route;
use Modules\Qol\Http\Controllers\PreferenceController;
use Modules\Qol\Http\Controllers\QolController;

// Match FreeScout's RouteServiceProvider: installations in a subdirectory need
// that prefix on module routes too (for example /helpdesk/qol/settings).
$qolSubdirectory = class_exists('Helper') ? \Helper::getSubdirectory() : '';

Route::group(['prefix' => $qolSubdirectory, 'middleware' => ['web']], function () {
Route::group(['prefix' => 'qol', 'middleware' => ['auth']], function () {
    // Use closures instead of [Controller::class, 'method']. Some hosted
    // FreeScout overrides run an older route dispatcher which fails to build
    // an action from the class-array form.
    Route::get('/preferences/arrange-by', function () {
        return app(PreferenceController::class)->getArrangeBy(request());
    })->name('qol.preferences.arrange_by');
    Route::post('/preferences/arrange-by', function () {
        return app(PreferenceController::class)->setArrangeBy(request());
    })->name('qol.preferences.arrange_by.save');
    Route::get('/contact/new', function () {
        return app(QolController::class)->createContact();
    })->name('qol.contact.create');
    Route::post('/contact/new', function () {
        return app(QolController::class)->storeContact(request());
    })->name('qol.contact.store');
    Route::post('/conversations/merge', function () {
        return app(QolController::class)->bulkMerge(request());
    })->name('qol.conversations.merge');
    Route::get('/settings', function () {
        return app(QolController::class)->settings();
    })->name('qol.settings');
    Route::post('/settings', function () {
        return app(QolController::class)->saveSettings(request());
    })->name('qol.settings.save');
});
});
