<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Front\AuthController;
use App\Http\Controllers\Front\MainController;
use App\Http\Controllers\Front\BrandsController;
use App\Http\Controllers\Front\ProductController;

Route::get('/',[MainController::class,'index'])->name('home');
Route::get('/internal-credit',[MainController::class,'credit'])->name('internal-credit');
Route::get('/brands',[BrandsController::class,'index'])->name('brands');

Route::middleware('guest')->group(function(){
    Route::get('/login',[AuthController::class,'login'])->name('front.login');
});

Route::middleware('auth')->group(function(){
   Route::get('/profile',[AuthController::class,'profile'])->name('profile');
});

require __DIR__.'/admin.php';
require __DIR__.'/courier.php';

Route::get('/{slug}',[ProductController::class,'product'])->name('product');
