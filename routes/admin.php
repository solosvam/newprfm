<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AjaxController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\MainController;
use App\Http\Controllers\Admin\Product\BrandsController;
use App\Http\Controllers\Admin\Product\SizesController;
use App\Http\Controllers\Admin\Product\TypesController;
use App\Http\Controllers\Admin\Product\IngredientController;
use App\Http\Controllers\Admin\Product\CategoriesController;
use App\Http\Controllers\Admin\Product\ProductsController;
use App\Http\Controllers\Admin\PermissionsController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\CourierController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\BannersController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('guest:admin')->group(function(){
    Route::get('/',[AuthController::class,'loginpage'])->name('loginpage');
    Route::get('/login',[AuthController::class,'loginpage'])->name('loginpage');
    Route::post('/login',[AuthController::class,'login'])->name('login');
});

Route::prefix('admin')->middleware('auth:admin')->group(function(){
    Route::get('/logout',[AuthController::class,'logout'])->name('logout');
    Route::get('/',[MainController::class,'index'])->name('index');
    Route::get('/main',[MainController::class,'index'])->name('main');

    Route::controller(AdminController::class)->middleware(['can:admin.list'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(CourierController::class)->middleware(['can:courier.list'])->prefix('courier')->name('courier.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(PermissionsController::class)->middleware(['can:permission.list'])->prefix('permission')->name('permission.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','add')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(RolesController::class)->middleware(['can:role.list'])->prefix('role')->name('role.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::get('permissions/{id}','permissions')->name('permissions');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(AjaxController::class)->prefix('ajax')->name('ajax.')->group(function () {
        Route::post('set-role-permission','setRolePermission')->name('set-role-permission');
    });

    Route::controller(SizesController::class)->middleware(['can:size.list'])->prefix('size')->name('size.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(FaqController::class)->middleware(['can:faq.list'])->prefix('faq')->name('faq.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(TypesController::class)->middleware(['can:type.list'])->prefix('type')->name('type.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(BrandsController::class)->middleware(['can:brand.list'])->prefix('brand')->name('brand.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(IngredientController::class)->middleware(['can:ingredient.list'])->prefix('ingredient')->name('ingredient.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(CategoriesController::class)->middleware(['can:category.list'])->prefix('category')->name('category.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(BannersController::class)->middleware(['can:banner.list'])->prefix('banner')->name('banner.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });

    Route::controller(ProductsController::class)->middleware(['can:product.list'])->prefix('product')->name('product.')->group(function () {
        Route::get('list','index')->name('list');
        Route::get('add','add')->name('add');
        Route::get('edit/{id}','edit')->name('edit');
        Route::post('add','create')->name('add');
        Route::post('update/{id}','update')->name('update');
    });
});
