<?php

use Illuminate\Support\Facades\Route;
use FreescoutQOL\Controllers\CalendarController;
use FreescoutQOL\Controllers\PreferenceController;
use FreescoutQOL\Controllers\QolController;

// Match FreeScout's RouteServiceProvider for installations hosted in a subdirectory.
$qolSubdirectory = class_exists('Helper') ? \Helper::getSubdirectory() : '';

Route::group(['prefix' => $qolSubdirectory, 'middleware' => ['web']], function () {
Route::group(['prefix' => 'qol', 'middleware' => ['auth']], function () {
    Route::get('/calendar', function () { return app(CalendarController::class)->index(request()); })->name('qol.calendar');
    Route::get('/calendar/events', function () { return app(CalendarController::class)->events(request()); })->name('qol.calendar.events');
    Route::get('/calendar/calendars', function () { return app(CalendarController::class)->calendars(request()); })->name('qol.calendar.calendars');
    Route::post('/calendar/events', function () { return app(CalendarController::class)->save(request()); })->name('qol.calendar.save');
    Route::post('/calendar/events/delete', function () { return app(CalendarController::class)->delete(request()); })->name('qol.calendar.delete');
    Route::get('/calendar/setup', function () { return app(CalendarController::class)->settings(request()); })->name('qol.calendar.settings');
    Route::post('/calendar/setup', function () { return app(CalendarController::class)->settings(request()); })->name('qol.calendar.settings.save');
    Route::post('/calendar/connect/{provider}', function ($provider) { return app(CalendarController::class)->connect(request(), $provider); })->name('qol.calendar.connect');
    Route::get('/calendar/callback/{provider}', function ($provider) { return app(CalendarController::class)->callback(request(), $provider); })->name('qol.calendar.callback');
    Route::post('/calendar/disconnect', function () { return app(CalendarController::class)->disconnect(request()); })->name('qol.calendar.disconnect');

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
});
});
