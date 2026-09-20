<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\AuthController;
use App\Http\Controllers\Frontend\MainController;
use App\Http\Controllers\Frontend\BrandsController;
use App\Http\Controllers\Frontend\ProductController;
use App\Http\Controllers\Frontend\CheckoutController;

Route::get('/',[MainController::class,'index'])->name('home');
Route::get('/internal-credit',[MainController::class,'credit'])->name('internal-credit');
Route::get('/brands',[BrandsController::class,'index'])->name('brands');
Route::get('/brand/{slug}',[BrandsController::class,'products'])->name('brand.products');
Route::view('/cart', 'frontend.cart')->name('cart');
Route::get('/cart/products',[ProductController::class,'cartProducts'])->name('cart.products');

Route::middleware('guest')->group(function(){
    Route::get('/login',[AuthController::class,'login'])->name('front.login');
    Route::post('/login/check',[AuthController::class,'checkMobile'])->name('front.login.check');
    Route::post('/login/password',[AuthController::class,'passwordLogin'])->name('front.login.password');
    Route::post('/login/otp',[AuthController::class,'verifyOtp'])->name('front.login.otp');
    Route::post('/login/otp/resend',[AuthController::class,'resendOtp'])->name('front.login.otp.resend');
    Route::post('/login/set-password',[AuthController::class,'setPassword'])->name('front.login.set-password');
});

Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('front.logout');

Route::middleware('auth')->group(function(){
   Route::get('/checkout',[CheckoutController::class,'index'])->name('checkout');
   Route::post('/checkout',[CheckoutController::class,'store'])->name('checkout.store');
   Route::get('/checkout/success/{order}',[CheckoutController::class,'success'])->name('checkout.success');
   Route::get('/profile',[AuthController::class,'profile'])->name('profile');
   Route::get('/profile/personal',[AuthController::class,'personal'])->name('profile.personal');
   Route::post('/profile/personal',[AuthController::class,'updatePersonal'])->name('profile.personal.update');
   Route::get('/profile/orders',[AuthController::class,'orders'])->name('profile.orders');
   Route::get('/profile/wishlist',[AuthController::class,'wishlist'])->name('profile.wishlist');
   Route::get('/profile/reviews',[AuthController::class,'reviews'])->name('profile.reviews');
   Route::post('/product/{product}/review',[ProductController::class,'review'])->name('product.review');
});

require __DIR__.'/admin.php';

Route::get('/{slug}',[ProductController::class,'product'])->name('product');
