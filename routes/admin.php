<?php

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

use App\Http\Controllers\Backend\Product\BrandsController;
use App\Http\Controllers\Backend\Product\SizesController;
use App\Http\Controllers\Backend\Product\TypesController;
use App\Http\Controllers\Backend\Product\IngredientController;
use App\Http\Controllers\Backend\Product\CategoriesController;
use App\Http\Controllers\Backend\Product\ProductsController;
use App\Http\Controllers\Backend\Product\ProductImportController;


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

            /*
            |--------------------------------------------------------------------------
            | Main
            |--------------------------------------------------------------------------
            */

            Route::get('/', [MainController::class, 'index'])
                ->name('dashboard');

            Route::get('/main', [MainController::class, 'index'])
                ->name('main');

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

            Route::controller(SettingsController::class)
                ->middleware('can:system.settings')
                ->prefix('settings')
                ->name('settings.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'update')->name('update');
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
                    Route::post('/set-role-permission', 'setRolePermission')->name('set-role-permission');
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
                    Route::get('/edit/{id}', 'edit')->name('edit');
                    Route::post('/add', 'create')->name('create');
                    Route::post('/update/{id}', 'update')->name('update');
                    Route::post('/delete/{id}', 'destroy')->name('destroy');
                });

        });
    });
