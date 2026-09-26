<?php

use Illuminate\Support\Facades\Route;
use Modules\Qol\Http\Controllers\CalendarController;
use Modules\Qol\Http\Controllers\PreferenceController;
use Modules\Qol\Http\Controllers\QolController;

// Match FreeScout's RouteServiceProvider: installations in a subdirectory need
// that prefix on module routes too (for example /helpdesk/qol/settings).
$qolSubdirectory = class_exists('Helper') ? \Helper::getSubdirectory() : '';

Route::group(['prefix' => $qolSubdirectory, 'middleware' => ['web']], function () {
Route::group(['prefix' => 'qol', 'middleware' => ['auth']], function () {
    Route::get('/calendar', function () { return app(CalendarController::class)->index(request()); })->name('qol.calendar');
    Route::get('/calendar/ticket-event', function () { return app(CalendarController::class)->ticketEvent(request()); })->name('qol.calendar.ticket-event');
    Route::get('/calendar/ticket-events', function () { return app(CalendarController::class)->ticketEvents(request()); })->name('qol.calendar.ticket-events');
    Route::post('/calendar/ticket-events/import', function () { return app(CalendarController::class)->importTicketEvents(request()); })->name('qol.calendar.ticket-events.import');
    Route::get('/calendar/events', function () { return app(CalendarController::class)->events(request()); })->name('qol.calendar.events');
    Route::get('/calendar/calendars', function () { return app(CalendarController::class)->calendars(request()); })->name('qol.calendar.calendars');
    Route::post('/calendar/events', function () { return app(CalendarController::class)->save(request()); })->name('qol.calendar.save');
    Route::post('/calendar/default', function () { return app(CalendarController::class)->saveDefault(request()); })->name('qol.calendar.default.save');
    Route::post('/calendar/events/delete', function () { return app(CalendarController::class)->delete(request()); })->name('qol.calendar.delete');
    Route::get('/calendar/setup', function () { return app(CalendarController::class)->settings(request()); })->name('qol.calendar.settings');
    Route::post('/calendar/setup', function () { return app(CalendarController::class)->settings(request()); })->name('qol.calendar.settings.save');
    Route::post('/calendar/connect/{provider}', function ($provider) { return app(CalendarController::class)->connect(request(), $provider); })->name('qol.calendar.connect');
    Route::get('/calendar/callback/{provider}', function ($provider) { return app(CalendarController::class)->callback(request(), $provider); })->name('qol.calendar.callback');
    Route::post('/calendar/disconnect', function () { return app(CalendarController::class)->disconnect(request()); })->name('qol.calendar.disconnect');

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
