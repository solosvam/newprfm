
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

use App\Http\Controllers\Frontend\AuthController;
use App\Http\Controllers\Frontend\MainController;
use App\Http\Controllers\Frontend\CategoryController;
use App\Http\Controllers\Frontend\BrandsController;
use App\Http\Controllers\Frontend\ProductController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\FavoriteController;
use App\Http\Controllers\Frontend\CreditProfileController;
use App\Http\Controllers\Frontend\SearchController;


/*
|--------------------------------------------------------------------------
| Language
|--------------------------------------------------------------------------
*/

Route::post('/language', function (Request $request) {
    $data = $request->validate([
        'locale' => ['required', Rule::in(['az', 'en', 'ru'])],
    ]);

    $request->session()->put('locale', $data['locale']);

    return response()->json([
        'locale' => $data['locale'],
    ]);
})->name('language.change');


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

Route::controller(SearchController::class)->group(function () {
    Route::get('/search/suggestions', 'suggestions')->name('search.suggestions');
    Route::post('/search/click', 'click')->name('search.click');
});


/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

Route::controller(MainController::class)->group(function () {
    Route::get('/', 'index')->name('home');
    Route::get('/internal-credit', 'credit')->name('internal-credit');
});


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

Route::controller(CategoryController::class)->group(function () {
    Route::get('/category/{slug}', 'show')->name('category');
});


/*
|--------------------------------------------------------------------------
| Brands
|--------------------------------------------------------------------------
*/

Route::controller(BrandsController::class)->group(function () {
    Route::get('/brands', 'index')->name('brands');
    Route::get('/brand/{slug}', 'products')->name('brand.products');
});


/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

Route::controller(ProductController::class)->group(function () {
    Route::get('/cart/products', 'cart')->name('cart.products');
    Route::get('/wishlist/products', 'wishlist')->name('wishlist.products');
});


/*
|--------------------------------------------------------------------------
| Cart & Wishlist
|--------------------------------------------------------------------------
*/

Route::view('/cart', 'frontend.cart')->name('cart');

Route::controller(FavoriteController::class)->group(function () {
    Route::get('/wishlist', 'guest')->name('wishlist');
});


/*
|--------------------------------------------------------------------------
| Authentication - Guest
|--------------------------------------------------------------------------
*/

Route::middleware('guest')
    ->controller(AuthController::class)
    ->group(function () {
        Route::get('/login', 'login')->name('front.login');
        Route::post('/login/check', 'checkMobile')->name('front.login.check');
        Route::post('/login/password', 'passwordLogin')->name('front.login.password');
        Route::post('/login/otp', 'verifyOtp')->name('front.login.otp');
        Route::post('/login/otp/resend', 'resendOtp')->name('front.login.otp.resend');
        Route::post('/login/set-password', 'setPassword')->name('front.login.set-password');
    });


/*
|--------------------------------------------------------------------------
| Authentication - Logout
|--------------------------------------------------------------------------
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('/logout', 'logout')
        ->middleware('auth')
        ->name('front.logout');
});


/*
|--------------------------------------------------------------------------
| Authenticated Customer
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Checkout
    Route::controller(CheckoutController::class)->group(function () {
        Route::get('/checkout', 'index')->name('checkout');
        Route::post('/checkout', 'store')->name('checkout.store');
        Route::get('/checkout/success/{order}', 'success')->name('checkout.success');
    });

    // Profile & Orders
    Route::controller(AuthController::class)->group(function () {
        Route::get('/profile', 'profile')->name('profile');

        Route::get('/profile/personal', 'personal')->name('profile.personal');
        Route::post('/profile/personal', 'updatePersonal')->name('profile.personal.update');

        Route::get('/profile/orders', 'orders')->name('profile.orders');
        Route::get('/profile/orders/{order}', 'order')->name('profile.orders.show');

        Route::get('/profile/bonus', 'bonus')->name('profile.bonus');

        Route::get('/profile/reviews', 'reviews')->name('profile.reviews');
        Route::delete('/profile/reviews/{review}', 'destroyReview')->name('profile.reviews.destroy');
    });

    // Credit Profile
    Route::controller(CreditProfileController::class)->group(function () {
        Route::get('/profile/credit', 'edit')->name('profile.credit');
        Route::post('/profile/credit', 'update')->name('profile.credit.update');

        Route::get('/profile/credit/image/{side}', 'image')
            ->whereIn('side', ['front', 'back'])
            ->name('profile.credit.image');
    });

    // Favorites
    Route::controller(FavoriteController::class)->group(function () {
        Route::get('/profile/wishlist', 'index')->name('profile.wishlist');

        Route::get('/favorites/ids', 'ids')->name('favorites.ids');
        Route::post('/favorites/sync', 'sync')->name('favorites.sync');
        Route::post('/favorites/{product}', 'store')->name('favorites.store');
        Route::delete('/favorites/{product}', 'destroy')->name('favorites.destroy');
    });

    // Product Reviews
    Route::controller(ProductController::class)->group(function () {
        Route::post('/product/{product}/review', 'review')->name('product.review');
    });
});


/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

require __DIR__ . '/admin.php';


/*
|--------------------------------------------------------------------------
| Product Details - Must Be Last
|--------------------------------------------------------------------------
*/

Route::controller(ProductController::class)->group(function () {
    Route::get('/{slug}', 'product')->name('product');
});
