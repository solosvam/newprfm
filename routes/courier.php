<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Courier\AuthController;
use App\Http\Controllers\Courier\MainController;

Route::prefix('courier')->middleware('guest:courier')->group(function(){
    Route::get('/',[AuthController::class,'loginpage'])->name('loginpage');
    Route::get('/courierlogin',[AuthController::class,'loginpage'])->name('loginpage');
    Route::post('/courierlogin',[AuthController::class,'login'])->name('courierlogin');
});

Route::prefix('courier')->middleware('auth:courier')->group(function(){
    Route::get('/',[MainController::class,'index'])->name('index');
});
