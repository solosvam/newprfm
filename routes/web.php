<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\AuthController;
use App\Http\Controllers\Frontend\MainController;
use App\Http\Controllers\Frontend\BrandsController;
use App\Http\Controllers\Frontend\ProductController;

Route::get('/',[MainController::class,'index'])->name('home');
Route::get('/internal-credit',[MainController::class,'credit'])->name('internal-credit');
Route::get('/brands',[BrandsController::class,'index'])->name('brands');
Route::get('/brand/{slug}',[MainController::class,'brand'])->name('brand.products');

Route::middleware('guest')->group(function(){
    Route::get('/login',[AuthController::class,'login'])->name('front.login');
});

Route::middleware('auth')->group(function(){
   Route::get('/profile',[AuthController::class,'profile'])->name('profile');
   Route::post('/product/{product}/review',[ProductController::class,'review'])->name('product.review');
});

require __DIR__.'/admin.php';

Route::get('/{slug}',[ProductController::class,'product'])->name('product');
