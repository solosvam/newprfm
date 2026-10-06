<?php

use App\Models\Product\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

// Anbar portalı: qısa link /w/{token} (WarehousePortalService::TOKEN_PATTERN)
Route::middleware([\App\Http\Middleware\WarehousePortalHeaders::class, 'throttle:60,1'])
    ->where(['token' => \App\Services\WarehousePortalService::TOKEN_PATTERN])
    ->name('warehouse.')->group(function () {
        Route::get('/w/{token}', [\App\Http\Controllers\WarehousePortalController::class, 'index'])->name('portal');
        Route::post('/w/{token}/items/{item}', [\App\Http\Controllers\WarehousePortalController::class, 'answer'])->name('answer');
        Route::post('/w/{token}/allocations/{allocation}', [\App\Http\Controllers\WarehousePortalController::class, 'confirm'])->name('confirm');
        // Köhnə uzun linklər: qısa ünvana yönləndirilir
        Route::get('/warehouse-portal/{token}', fn (string $token) => redirect()->route('warehouse.portal', $token, 301))->name('legacy');
    });

use App\Http\Controllers\Frontend\AuthController;
use App\Http\Controllers\Frontend\RegisterController;
use App\Http\Controllers\Frontend\MainController;
use App\Http\Controllers\Frontend\CategoryController;
use App\Http\Controllers\Frontend\BrandsController;
use App\Http\Controllers\Frontend\ProductController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\OneClickOrderController;
use App\Http\Controllers\Frontend\FavoriteController;
use App\Http\Controllers\Frontend\CreditProfileController;
use App\Http\Controllers\Frontend\CreditApplicationController;
use App\Http\Controllers\Frontend\SearchController;
use App\Http\Controllers\Frontend\OrdersController;
use App\Http\Controllers\Frontend\BirbankPaymentController;
use App\Http\Controllers\Frontend\PromoCodeController;
use App\Http\Controllers\Frontend\ProductImageController;


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
| Optimized Product Images
|--------------------------------------------------------------------------
*/

Route::get('/image/product/{size}/{image}', [ProductImageController::class, 'show'])
    ->whereNumber('size')
    ->where('image', '[A-Za-z0-9._-]+')
    ->name('product.image');


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
Route::post('/order/one-click', [OneClickOrderController::class, 'store'])->middleware('throttle:5,1')->name('one-click.store');
Route::get('/order/one-click/success/{order}', [OneClickOrderController::class, 'success'])->name('one-click.success');
Route::get('/recommendations', [\App\Http\Controllers\Frontend\RecommendationController::class, 'sidebar'])->middleware('throttle:30,1')->name('recommendations.sidebar');
Route::post('/cart/promo', [PromoCodeController::class, 'apply'])->middleware('throttle:10,1')->name('promo.apply');
Route::delete('/cart/promo', [PromoCodeController::class, 'remove'])->name('promo.remove');

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
        Route::post('/login/inactive/verify', 'verifyInactive')->name('front.login.inactive.verify');
        Route::post('/login/inactive/resend', 'resendInactive')->name('front.login.inactive.resend');
        // Şifrə və SMS kodu təxmin edilməsin
        Route::post('/login/password', 'passwordLogin')->middleware('throttle:10,1')->name('front.login.password');
        Route::post('/login/forgot', 'forgotPassword')->middleware('throttle:5,1')->name('front.login.forgot');
        Route::post('/login/otp', 'verifyOtp')->middleware('throttle:10,1')->name('front.login.otp');
        Route::post('/login/otp/resend', 'resendOtp')->middleware('throttle:5,1')->name('front.login.otp.resend');
        Route::post('/login/set-password', 'setPassword')->name('front.login.set-password');
    });


Route::middleware(['web', 'guest', 'throttle:10,1'])
    ->controller(RegisterController::class)
    ->group(function () {
        Route::get('/register', 'create')->name('front.register');
        Route::post('/register', 'store')->name('front.register.store');
        Route::post('/register/check-mobile', 'checkMobile')->withoutMiddleware('throttle:10,1')->middleware('throttle:30,1')->name('front.register.check-mobile');
        Route::get('/register/verify', 'showVerify')->name('front.register.verify');
        Route::post('/register/verify', 'verify')->name('front.register.verify.store');
        Route::post('/register/resend', 'resend')->name('front.register.resend');
    });

// Dostunu dəvət et: qısa dəvət linki /r/{code}
Route::get('/r/{code}', [\App\Http\Controllers\Frontend\ReferralController::class, 'track'])
    ->where('code', '[A-Za-z0-9]{4,12}')
    ->middleware('throttle:30,1')
    ->name('referral.track');


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
    Route::get('/cart/items', [\App\Http\Controllers\Frontend\CartController::class, 'index'])->name('cart.items');
    Route::post('/cart/merge', [\App\Http\Controllers\Frontend\CartController::class, 'merge'])->middleware('throttle:30,1')->name('cart.merge');
    Route::post('/cart/items', [\App\Http\Controllers\Frontend\CartController::class, 'change'])->middleware('throttle:120,1')->name('cart.change');

    Route::post('/payment/birbank/start/{order}', [BirbankPaymentController::class, 'start'])
        ->middleware('throttle:5,1')->name('payment.birbank.start');

    // Checkout
    Route::controller(CheckoutController::class)->group(function () {
        Route::get('/checkout', 'index')->name('checkout');
        Route::post('/checkout', 'store')->name('checkout.store');
        Route::get('/checkout/success/{order}', 'success')->name('checkout.success');
    });

    // Profile
    Route::controller(AuthController::class)->group(function () {
        Route::get('/profile', 'profile')->name('profile');

        Route::get('/profile/personal', 'personal')->name('profile.personal');
        Route::post('/profile/personal', 'updatePersonal')->name('profile.personal.update');

        Route::get('/profile/bonus', 'bonus')->name('profile.bonus');

        Route::get('/profile/reviews', 'reviews')->name('profile.reviews');
        Route::delete('/profile/reviews/{review}', 'destroyReview')->name('profile.reviews.destroy');
    });

    Route::get('/profile/referral', [\App\Http\Controllers\Frontend\ReferralController::class, 'index'])->name('profile.referral');

    // "Qiymət enəndə xəbər ver"
    Route::post('/price-alerts/toggle', [\App\Http\Controllers\Frontend\PriceAlertController::class, 'toggle'])
        ->middleware('throttle:30,1')->name('price-alert.toggle');

    // Orders

    Route::controller(OrdersController::class)->group(function () {
        Route::get('/orders', 'index')->name('orders');
        Route::get('/order/{order}', 'details')->name('order.details');
    });

    Route::post('/credit/applications', [CreditApplicationController::class, 'store'])
        ->middleware('throttle:5,1')->name('credit.application.store');

    Route::get('/credit/application/address', [CreditApplicationController::class, 'address'])
        ->name('credit.application.address');
    Route::post('/credit/application/confirm', [CreditApplicationController::class, 'confirm'])
        ->middleware('throttle:5,1')->name('credit.application.confirm');

    // Credit Profile
    Route::controller(CreditProfileController::class)->group(function () {
        Route::get('/profile/credit', 'edit')->name('profile.credit');
        Route::post('/profile/credit', 'update')->name('profile.credit.update');
        Route::post('/profile/credit/ocr', 'ocr')->middleware('throttle:10,1')->name('profile.credit.ocr');
        Route::get('/profile/credit/image/{side}', 'image')->whereIn('side', ['front', 'back'])->name('profile.credit.image');

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


// SMS ödəniş linki (login lazım deyil) — PayLinkController
Route::controller(\App\Http\Controllers\Frontend\PayLinkController::class)
    ->prefix('p')
    ->where(['token' => '[A-Za-z0-9]{10}'])
    ->group(function () {
        Route::get('/{token}', 'show')->middleware('throttle:30,1')->name('pay.link');
        Route::post('/{token}', 'start')->middleware('throttle:5,1')->name('pay.link.start');
    });

Route::get('/payment/birbank/return/{payment}', [BirbankPaymentController::class, 'callback'])
    ->middleware('throttle:20,1')->name('payment.birbank.return');

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

Route::get('/index.php', function (Request $request)
{
    if ($request->query('route') !== 'product/product')
    {
        abort(404);
    }

    $oldId = $request->query('product_id');

    if (!ctype_digit((string) $oldId) || (int) $oldId < 1)
    {
        abort(404);
    }

    $product = Product::where('old_id', (int) $oldId)
        ->firstOrFail();

    return redirect()->route('product', [
        'slug' => $product->slug,
    ], 301);
});

Route::controller(ProductController::class)->group(function () {
    Route::get('/{slug}', 'product')->name('product');
});

// Uyğun route tapılmayan URL-lər də web middleware-dən (sessiya, dil) keçsin ki, 404 səhifəsi saytın layout-u ilə açılsın
Route::fallback(fn () => abort(404));
