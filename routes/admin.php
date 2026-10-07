<?php

use App\Http\Controllers\Backend\AssistantController;
use App\Http\Controllers\Backend\FerrumController;
use App\Http\Controllers\Backend\FinanceController;
use App\Http\Controllers\Backend\ProcurementController;
use App\Http\Middleware\AssistantFrame;
use App\Services\AdminMenuService;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\AjaxController;
use App\Http\Controllers\Backend\AuthController;
use App\Http\Controllers\Backend\MainController;
use App\Http\Controllers\Backend\PermissionsController;
use App\Http\Controllers\Backend\RolesController;
use App\Http\Controllers\Backend\FaqController;
use App\Http\Controllers\Backend\BannersController;
use App\Http\Controllers\Backend\CreditController;
use App\Http\Controllers\Backend\SettingsController;
use App\Http\Controllers\Backend\SmsTemplateController;
use App\Http\Controllers\Backend\CrmController;
use App\Http\Controllers\Backend\EasyOrdersController;
use App\Http\Controllers\Backend\OrdersController;
use App\Http\Controllers\Backend\CartsController;
use App\Http\Controllers\Backend\PagesController;
use App\Http\Controllers\Backend\PopupsController;
use App\Http\Controllers\Backend\PriceAlertsController;
use App\Http\Controllers\Backend\StatisticsController;
use App\Http\Controllers\Backend\FeaturedProductsController;
use App\Http\Controllers\Backend\ReferralsController;
use App\Http\Controllers\Backend\PromoCodesController;
use App\Http\Controllers\Backend\RefundController;

use App\Http\Controllers\Backend\Product\ProductDiscountsController;
use App\Http\Controllers\Backend\Product\BrandsController;
use App\Http\Controllers\Backend\Product\SizesController;
use App\Http\Controllers\Backend\Product\TypesController;
use App\Http\Controllers\Backend\Product\IngredientController;
use App\Http\Controllers\Backend\Product\CategoriesController;
use App\Http\Controllers\Backend\Product\ProductsController;
use App\Http\Controllers\Backend\Product\ReviewsController;
use App\Http\Controllers\Backend\Product\ProductImportController;
use App\Http\Controllers\Backend\Product\SearchAliasController;


/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Bütün işçilər bu hissədən daxil olacaq:
| admin, operator, courier və s.
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Guest Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('guest:admin')->group(function () {

            Route::get('/', [AuthController::class, 'login'])->name('login.form');

            Route::get('/login', [AuthController::class, 'login'])->name('login.form');

            Route::post('/login', [AuthController::class, 'loginSubmit'])->name('login.submit');
        });


        /*
        |--------------------------------------------------------------------------
        | Authenticated Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('auth:admin')->group(function () {

            // Kuryer: öz sifarişləri (CourierController rolu və sifarişin kuryerini yoxlayır)
            Route::controller(\App\Http\Controllers\Backend\CourierController::class)
                ->prefix('courier')->name('courier.')->middleware('throttle:60,1')->group(function () {
                    Route::get('/order/{order}', 'order')->name('order');
                    Route::post('/order/{order}/parts/{allocation}/pick', 'pick')->name('pick');
                    Route::post('/order/{order}/parts/{allocation}/pay', 'pay')->name('pay');
                    Route::post('/order/{order}/parts/{allocation}/problem', 'problem')->name('problem');
                    Route::post('/order/{order}/start', 'start')->name('start');
                    Route::post('/order/{order}/arrive', 'arrive')->name('arrive');
                    Route::post('/order/{order}/deliver', 'deliver')->name('deliver');
                    Route::post('/order/{order}/delivery-problem', 'deliveryProblem')->name('delivery-problem');
                    Route::post('/order/{order}/items/{item}/refuse', 'refuse')->name('refuse');
                    Route::post('/order/{order}/parts/{allocation}/returned', 'returned')->name('returned');
                    Route::post('/order/{order}/transfer', 'transfer')->name('transfer');
                });

            // Ferrum Chrome extension-u (extensions/ferrum-filler): sifariş + kredit məlumatları JSON
            Route::middleware(['can:ferrum', 'throttle:30,1'])->controller(FerrumController::class)
                ->prefix('ferrum')->name('ferrum.')->group(function () {
                    Route::get('/orders/{ref}', 'order')->where('ref', '[A-Za-z0-9-]{1,30}')->name('order');
                    Route::get('/orders/{order}/id-card/{side}', 'idCard')->whereNumber('order')->whereIn('side', ['front', 'back'])->name('id-card');
                });

            // Operator yan paneli (extensions/ps-side)
            Route::middleware('can:crm')->controller(AssistantController::class)
                ->prefix('assistant')->group(function () {
                    Route::get('/', 'index')->middleware(AssistantFrame::class)->name('assistant');
                    Route::get('/customer', 'customer')->middleware('throttle:60,1')->name('assistant.customer');
                    Route::post('/customer', 'storeCustomer')->middleware('throttle:20,1')->name('assistant.customer.store');
                    Route::post('/customer/{customer}/credit-profile/ocr', 'creditOcr')->whereNumber('customer')->middleware('throttle:10,1')->name('assistant.credit.ocr');
                    Route::post('/customer/{customer}/credit-profile', 'creditProfile')->whereNumber('customer')->middleware('throttle:20,1')->name('assistant.credit.update');
                    Route::get('/search', 'search')->middleware('throttle:60,1')->name('assistant.search');
                    Route::post('/search-image', 'searchImage')->middleware('throttle:20,1')->name('assistant.search-image');
                    Route::get('/poster/{product}', 'poster')->whereNumber('product')->middleware('throttle:60,1')->name('assistant.poster');
                });

            Route::middleware('can:finance')->controller(FinanceController::class)
                ->prefix('finance')->name('finance.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
                    Route::post('/{movement}/reverse', 'reverse')->middleware('throttle:30,1')->name('reverse');
                    Route::get('/account/{account}', 'account')->name('account');
                });

            Route::middleware('can:crm')->controller(ProcurementController::class)
                ->prefix('procurement')->name('procurement.')->group(function () {
                    Route::get('/warehouses', 'warehouses')->name('warehouses');
                    Route::post('/warehouses/{warehouse}/link', 'createLink')->name('warehouses.link');
                    Route::post('/warehouses/{warehouse}/revoke-links', 'revokeLinks')->name('warehouses.revoke-links');
                    Route::post('/warehouses', 'storeWarehouse')->name('warehouses.store');
                    Route::get('/warehouses/{warehouse}/edit', 'editWarehouse')->name('warehouses.edit');
                    Route::put('/warehouses/{warehouse}', 'updateWarehouse')->name('warehouses.update');
                    Route::get('/orders/{order}', 'show')->name('show');
                    Route::post('/orders/{order}/requests', 'createRequests')->name('requests.store');
                    Route::post('/orders/{order}/responses/{requestItem}', 'recordOffer')->name('offers.store');
                    Route::post('/orders/{order}/allocations', 'allocate')->name('allocations.store');
                    Route::post('/orders/{order}/allocations/{allocation}/cancel', 'cancelAllocation')->name('allocations.cancel');
                    Route::post('/orders/{order}/allocations/{allocation}/status', 'transitionAllocation')->name('allocations.status');
                    Route::post('/orders/{order}/allocations/{allocation}/sms', 'allocationSms')->name('allocations.sms');
                    Route::post('/orders/{order}/requests/{warehouseRequest}/sms', 'requestSms')->name('requests.sms');
                });

            /*
            |--------------------------------------------------------------------------
            | Main
            |--------------------------------------------------------------------------
            */

            Route::get('/', [MainController::class, 'index'])
                ->name('dashboard');

            Route::get('/shortcuts', [MainController::class, 'shortcuts'])
                ->name('shortcuts');

            Route::get('/search-pages', function (AdminMenuService $menu) {
                return response()->json($menu->searchPages())
                    ->header('Cache-Control', 'private, no-store');
            })->name('search-pages');

            Route::get('/main', [MainController::class, 'index'])
                ->name('main');

            Route::get('/statistics', [StatisticsController::class, 'index'])
                ->middleware('can:statistics')
                ->name('statistics');

            Route::get('/logout', [AuthController::class, 'logout'])
                ->name('logout');


            /*
            |--------------------------------------------------------------------------
            | Users
            |--------------------------------------------------------------------------
            |
            | Hələlik AdminController qalır.
            | Sonra UserController olaraq dəyişəcəyik.
            |
            */

            Route::controller(UserController::class)
                ->middleware('can:user.list')
                ->prefix('user')
                ->name('user.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            /*
            |--------------------------------------------------------------------------
            | Permissions
            |--------------------------------------------------------------------------
            */

            Route::controller(PermissionsController::class)
                ->middleware('can:permission.list')
                ->prefix('permission')
                ->name('permission.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'add')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            Route::controller(RolesController::class)
                ->middleware('can:role.list')
                ->prefix('role')
                ->name('role.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::get('/permissions/{id}', 'permissions')->name('permissions');
                    Route::post('/permissions/{id}', 'togglePermissions')->middleware('throttle:60,1')->name('permissions.toggle');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            /*
            |--------------------------------------------------------------------------
            | Credit
            |--------------------------------------------------------------------------
            */

            Route::controller(CreditController::class)
                ->middleware('can:credit.menu')
                ->prefix('credit')
                ->name('credit.')
                ->group(function () {
                    Route::get('/applications', 'applications')->name('applications');
                    Route::get('/periods', 'periods')->name('periods');
                    Route::post('/periods', 'updatePeriods')->name('periods.update');
                    Route::get('/terms', 'terms')->name('terms');
                    Route::post('/terms', 'updateTerms')->name('terms.update');
                });


            Route::controller(SmsTemplateController::class)
                ->middleware('can:system.sms')
                ->prefix('sms-template')
                ->name('sms-template.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/{smsTemplate}', 'update')->name('update');
                });

            Route::prefix('promo-codes')->name('promo-codes.')->controller(PromoCodesController::class)->group(function () {
                Route::get('/', 'index')->middleware('can:promo.list')->name('index');
                Route::get('/create', 'create')->middleware('can:promo.manage')->name('create');
                Route::post('/', 'store')->middleware('can:promo.manage')->name('store');
                Route::get('/{promo}/edit', 'edit')->middleware('can:promo.manage')->name('edit');
                Route::put('/{promo}', 'update')->middleware('can:promo.manage')->name('update');
                Route::get('/{promo}/history', 'history')->middleware('can:promo.list')->name('history');
            });

            Route::controller(SettingsController::class)
                ->middleware('can:system.settings')
                ->prefix('settings')
                ->name('settings.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    // Bölmələr: admin.settings.{bonuses|referral|orders|banners}[.update]
                    foreach (array_keys(SettingsController::SECTIONS) as $section) {
                        Route::get('/'.$section, 'show')->defaults('section', $section)->name($section);
                        Route::post('/'.$section, 'update')->defaults('section', $section)->name($section.'.update');
                    }
                });

            // Satış: ümumi sifariş siyahısı və səbətdəki mallar
            Route::middleware('can:crm')->group(function () {
                Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
                Route::get('/carts', [CartsController::class, 'index'])->name('carts.index');
                Route::get('/price-alerts', [PriceAlertsController::class, 'index'])->name('price-alerts.index');
                Route::get('/referrals', [ReferralsController::class, 'index'])->name('referrals.index');
            });

            Route::prefix('easy-orders')->name('easy-orders.')->controller(EasyOrdersController::class)->middleware('can:crm')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{order}', 'show')->name('show');
                Route::get('/{order}/customer-lookup', 'lookup')->name('lookup');
                Route::post('/{order}/confirm', 'confirm')->name('confirm');
                Route::delete('/{order}', 'destroy')->name('destroy');
            });

            Route::controller(RefundController::class)
                ->middleware('can:refund')
                ->prefix('refund')
                ->name('refund.')
                ->group(function () {
                    Route::post('/', 'store')->name('store');
                    Route::get('/payment/{payment}', 'byPayment')->name('by-payment');
                });

            Route::controller(CrmController::class)
                ->middleware('can:crm')
                ->prefix('crm')
                ->name('crm.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/customer/{id}', 'customer')->name('customer');
                    Route::post('/customer', 'storeCustomer')->middleware('throttle:20,1')->name('customer.store');
                    Route::get('/customer/{customer}/tab/{tab}', 'tab')->name('tab');
                    Route::post('/customer/{customer}/order', 'storeOrder')->name('order.store');
                    Route::get('/customer/{customer}/order/{order}', 'order')->name('order');
                    Route::post('/customer/{customer}/order/{order}/confirm', 'confirmOneClick')->name('one-click.confirm');
                    Route::post('/customer/{customer}/order/{order}/item/{item}/cancel', 'cancelItem')->name('order.item.cancel');
                    Route::post('/customer/{customer}/order/{order}/cancel', 'cancelOrder')->name('order.cancel');
                    Route::post('/customer/{customer}/order/{order}/item/{item}/refuse', 'refuseItem')->name('order.item.refuse');
                    Route::post('/customer/{customer}/order/{order}/start', 'startOrder')->name('order.start');
                    Route::post('/customer/{customer}/order/{order}/courier', 'assignCourier')->name('order.courier');
                    Route::post('/customer/{customer}/order/{order}/cancellation/{cancellation}/refund', 'refundCancellation')
                        ->middleware(['can:refund', 'throttle:10,1'])->name('order.cancellation.refund');
                    Route::post('/customer/{customer}/order/{order}/pay-link', 'sendPayLink')->middleware('throttle:10,1')->name('order.pay-link');
                    Route::post('/customer/{customer}', 'update')->name('update');
                    Route::post('/customer/{customer}/address/{address}', 'updateAddress')->whereNumber('address')->name('address.update');
                    Route::post('/customer/{customer}/credit-profile/ocr', 'creditProfileOcr')->middleware('throttle:10,1')->name('credit-profile.ocr');
                    Route::post('/customer/{customer}/credit-profile', 'updateCreditProfile')->name('credit-profile.update');
                    Route::get('/customer/{customer}/id-card/{side}', 'idCardImage')->whereIn('side', ['front', 'back'])->middleware('can:crm.id_card')->name('id-card');
                    Route::post('/customer/{customer}/reset-password', 'resetPassword')->name('reset-password');
                    Route::get('/customer/{customer}/sms', 'sms')->name('sms');
                    Route::post('{id}/reset-password', 'resetPassword')->name('reset.password');
                });


            /*
            |--------------------------------------------------------------------------
            | AJAX
            |--------------------------------------------------------------------------
            */

            Route::controller(AjaxController::class)
                ->prefix('ajax')
                ->name('ajax.')
                ->group(function () {
                    Route::get('search-customer/crm',   'searchCustomerCrm')->name('search.customer.crm');
                    Route::get('search-product/crm',    'searchProductCrm')->middleware('can:crm')->name('search.product.crm');
                    Route::post('/set-role-permission', 'setRolePermission')->middleware('can:role.list')->name('set-role-permission');
                });


            /*
            |--------------------------------------------------------------------------
            | Sizes
            |--------------------------------------------------------------------------
            */

            Route::controller(SizesController::class)
                ->middleware('can:size.menu')
                ->prefix('size')
                ->name('size.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            /*
            |--------------------------------------------------------------------------
            | FAQ
            |--------------------------------------------------------------------------
            */

            Route::controller(FaqController::class)
                ->middleware('can:site.faq')
                ->prefix('faq')
                ->name('faq.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            /*
            |--------------------------------------------------------------------------
            | Product Types
            |--------------------------------------------------------------------------
            */

            Route::controller(TypesController::class)
                ->middleware('can:type.menu')
                ->prefix('type')
                ->name('type.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            /*
            |--------------------------------------------------------------------------
            | Brands
            |--------------------------------------------------------------------------
            */

            Route::controller(BrandsController::class)
                ->middleware('can:brands.menu')
                ->prefix('brand')
                ->name('brand.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                    Route::get('/{brand}/logo-search', 'searchLogo')->name('logo.search');
                    Route::post('/{brand}/logo', 'applyLogo')->name('logo.apply');
                    Route::post('/{brand}/aliases/suggest', 'suggestAliases')->middleware('can:product.search')->name('aliases.suggest');
                    Route::post('/{brand}/aliases', 'storeAliases')->middleware('can:product.search')->name('aliases.store');
                    Route::delete('/{brand}/aliases/{alias}', 'destroyAlias')->middleware('can:product.search')->name('aliases.destroy');
                });


            /*
            |--------------------------------------------------------------------------
            | Ingredients / Notes
            |--------------------------------------------------------------------------
            */

            Route::controller(IngredientController::class)
                ->middleware('can:ingredient.menu')
                ->prefix('ingredient')
                ->name('ingredient.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */

            Route::controller(CategoriesController::class)
                ->middleware('can:category.menu')
                ->prefix('category')
                ->name('category.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            /*
            |--------------------------------------------------------------------------
            | Banners
            |--------------------------------------------------------------------------
            */

            // Ana səhifənin vitrini ("Populyar")
            Route::controller(FeaturedProductsController::class)
                ->middleware('can:site.featured')
                ->prefix('featured')
                ->name('featured.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::post('/reorder', 'reorder')->name('reorder');
                    Route::get('/search', 'search')->middleware('throttle:120,1')->name('search');
                    Route::delete('/{featured}', 'destroy')->name('destroy');
                });

            Route::controller(PagesController::class)
                ->middleware('can:site.pages')
                ->prefix('pages')
                ->name('pages.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{page}/edit', 'edit')->name('edit');
                    Route::post('/{page}', 'update')->name('update');
                });

            Route::controller(PopupsController::class)
                ->middleware('can:site.popups')
                ->prefix('popups')
                ->name('popups.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{popup}/edit', 'edit')->name('edit');
                    Route::post('/{popup}', 'update')->name('update');
                    Route::delete('/{popup}', 'destroy')->name('destroy');
                });

            Route::controller(BannersController::class)
                ->middleware('can:site.banners')
                ->prefix('banner')
                ->name('banner.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('add');
                    Route::post('/update/{id}', 'update')->name('update');
                });


            Route::controller(ReviewsController::class)
                ->middleware('can:product.reviews')
                ->prefix('product/review')
                ->name('product.review.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::post('/{review}/approve', 'approve')->name('approve');
                    Route::delete('/{review}', 'destroy')->name('destroy');
                });

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            Route::controller(ProductsController::class)
                ->middleware('can:products.menu')
                ->prefix('product')
                ->name('product.')
                ->group(function () {
                    Route::get('/list', 'index')->name('list');
                    Route::get('/list-data', 'listData')->name('list.data');
                    Route::get('/add', 'add')->name('add');
                    Route::post('/import/fragrantica-preview', [ProductImportController::class, 'preview'])->name('import.fragrantica-preview');
                    Route::post('/import/ai-generate', [ProductImportController::class, 'generateWithAi'])->name('import.ai-generate');
                    Route::post('/import/image-search', [ProductImportController::class, 'searchImages'])->name('import.image-search');
                    Route::get('/{product}/poster', 'poster')->name('poster');
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('create');
                    Route::post('/update/{id}', 'update')->name('update');
                    Route::post('/delete/{id}', 'destroy')->name('destroy');
                });

            // Məhsul endirimləri: məhsulun "Endirim" tabı + "Endirimdəki məhsullar"
            Route::controller(ProductDiscountsController::class)
                ->middleware('can:product.discount')
                ->name('product-discounts.')
                ->group(function () {
                    Route::get('/product-discounts', 'index')->name('index');
                    Route::post('/product/{product}/discounts', 'store')->name('store');
                    Route::put('/product-discounts/{discount}', 'update')->name('update');
                    Route::post('/product-discounts/{discount}/end', 'end')->name('end');
                    Route::delete('/product-discounts/{discount}', 'destroy')->name('destroy');
                });

            // Axtarış lüğəti (search_aliases) + statistika
            Route::controller(SearchAliasController::class)
                ->middleware('can:product.search')
                ->prefix('product/search-aliases')
                ->name('product.search-aliases.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::delete('/no-result', 'destroyNoResult')->name('no-result.destroy');
                    Route::delete('/{alias}', 'destroy')->whereNumber('alias')->name('destroy');
                });

        });
    });
