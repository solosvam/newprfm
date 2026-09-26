<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\AuthController;
use App\Http\Controllers\Frontend\MainController;
use App\Http\Controllers\Frontend\CategoryController;
use App\Http\Controllers\Frontend\BrandsController;
use App\Http\Controllers\Frontend\ProductController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\FavoriteController;
use App\Http\Controllers\Frontend\CreditProfileController;

use App\Http\Controllers\Frontend\NewMainController;
use Illuminate\Validation\Rule;

Route::post('/language', function (Request $request) {
    $data = $request->validate([
        'locale' => ['required',Rule::in(['az', 'en', 'ru'])],
    ]);

    $request->session()->put('locale', $data['locale']);

    return response()->json(['locale' => $data['locale']]);
})->name('language.change');


// NEW START
Route::get('/newhome',[NewMainController::class,'index'])->name('newhome');
Route::get('/newhome/{slug}',[NewMainController::class,'product'])->name('newproduct');

// NEW END
Route::controller(BrandsController::class)->group(function () {
    Route::get('/brands', 'index')->name('brands');
    Route::get('/brand/{slug}', 'products')->name('brand.products');
});

Route::controller(ProductController::class)->group(function () {
    Route::get('/cart/products', 'cartProducts')->name('cart.products');
    Route::get('/wishlist/products', 'wishlistProducts')->name('wishlist.products');
});


Route::get('/',[MainController::class,'index'])->name('home');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category');
Route::get('/internal-credit',[MainController::class,'credit'])->name('internal-credit');
Route::view('/cart', 'frontend.new.cart')->name('cart');


Route::get('/wishlist',[FavoriteController::class,'guest'])->name('wishlist');

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
   Route::get('/profile/credit',[CreditProfileController::class,'edit'])->name('profile.credit');
   Route::post('/profile/credit',[CreditProfileController::class,'update'])->name('profile.credit.update');
   Route::get('/profile/credit/image/{side}',[CreditProfileController::class,'image'])->whereIn('side',['front','back'])->name('profile.credit.image');
   Route::get('/profile/personal',[AuthController::class,'personal'])->name('profile.personal');
   Route::post('/profile/personal',[AuthController::class,'updatePersonal'])->name('profile.personal.update');
   Route::get('/profile/orders',[AuthController::class,'orders'])->name('profile.orders');
   Route::get('/profile/orders/{order}',[AuthController::class,'order'])->name('profile.orders.show');
   Route::get('/profile/bonus',[AuthController::class,'bonus'])->name('profile.bonus');
   Route::get('/profile/wishlist',[FavoriteController::class,'index'])->name('profile.wishlist');
   Route::get('/favorites/ids',[FavoriteController::class,'ids'])->name('favorites.ids');
   Route::post('/favorites/sync',[FavoriteController::class,'sync'])->name('favorites.sync');
   Route::post('/favorites/{product}',[FavoriteController::class,'store'])->name('favorites.store');
   Route::delete('/favorites/{product}',[FavoriteController::class,'destroy'])->name('favorites.destroy');
   Route::get('/profile/reviews',[AuthController::class,'reviews'])->name('profile.reviews');
   Route::delete('/profile/reviews/{review}',[AuthController::class,'destroyReview'])->name('profile.reviews.destroy');
   Route::post('/product/{product}/review',[ProductController::class,'review'])->name('product.review');
});

require __DIR__.'/admin.php';

Route::get('/{slug}',[ProductController::class,'product'])->name('product');
