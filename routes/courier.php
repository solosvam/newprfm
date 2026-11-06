<?php

use App\Http\Controllers\Backend\CourierController;
use App\Http\Controllers\Backend\CourierTeamController;
use App\Http\Controllers\Courier\CourierAppController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kuryer tətbiqi (PWA): /kuryer
|--------------------------------------------------------------------------
| Admin panelindən ayrı, yalnız mobil üçün yazılmış səhifələr (resources/views/courier-app, public/courier-app).
| Giriş — admin girişi ilə eyni sessiya (/admin/login); kuryer deyilsə — admin panelə.
*/

Route::prefix('kuryer')->name('kuryer.')->group(function () {
    // Service worker /kuryer/ altında verilir ki, əhatəsi (scope) bütün tətbiq olsun
    Route::get('/sw.js', [CourierAppController::class, 'serviceWorker'])->name('sw');

    Route::middleware(['auth:admin', \App\Http\Middleware\CourierOnly::class, 'throttle:60,1'])->group(function () {
        Route::get('/', [CourierAppController::class, 'home'])->name('home');
        Route::get('/hesab', [CourierAppController::class, 'account'])->name('account');

        Route::controller(CourierController::class)->prefix('order/{order}')->group(function () {
            Route::get('/', 'order')->name('order');
            Route::post('/parts/{allocation}/pick', 'pick')->name('pick');
            Route::post('/parts/{allocation}/pay', 'pay')->name('pay');
            Route::post('/parts/{allocation}/problem', 'problem')->name('problem');
            Route::post('/start', 'start')->name('start');
            Route::post('/meeting-place', 'meetingPlace')->name('meeting-place');
            Route::post('/deliver', 'deliver')->name('deliver');
            Route::post('/delivery-problem', 'deliveryProblem')->name('delivery-problem');
            Route::post('/items/{item}/refuse', 'refuse')->name('refuse');
            Route::post('/parts/{allocation}/returned', 'returned')->name('returned');
            Route::post('/transfer', 'transfer')->name('transfer');
        });

        // Baş kuryer: kuryerlər — balans, sifarişlər, pulu özünə köçürmə, sifarişi ötürmə
        Route::controller(CourierTeamController::class)->prefix('kuryerler')->name('team')->middleware('can:courier.team')->group(function () {
            Route::get('/', 'index');
            Route::get('/{courier}', 'show')->whereNumber('courier')->name('.show');
            Route::post('/{courier}/collect', 'collect')->whereNumber('courier')->middleware('can:courier.collect')->name('.collect');
            Route::post('/{courier}/orders/{order}/transfer', 'transfer')->whereNumber(['courier', 'order'])->name('.transfer');
        });
    });
});
