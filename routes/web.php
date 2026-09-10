<?php

use Illuminate\Support\Facades\Route;
use FreescoutQOL\Controllers\PreferenceController;

Route::group(['prefix' => 'qol', 'middleware' => ['web', 'auth']], function () {
    Route::get('/preferences/arrange-by', [PreferenceController::class, 'getArrangeBy']);
    Route::post('/preferences/arrange-by', [PreferenceController::class, 'setArrangeBy']);
});
