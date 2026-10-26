<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::controller(ShopController::class)->group(function () {
    Route::get('/shop', 'index')->name('shop');
    Route::get('/search', 'index')->name('search');
    Route::get('/category/{category:slug}', 'category')->name('category');
    Route::get('/brand/{brand:slug}', 'brand')->name('brand');
    Route::get('/device/{deviceBrand:slug}/{deviceModel:slug}', 'device')->name('device');
    Route::get('/deals', 'deals')->name('deals');
    Route::get('/new-arrivals', 'newArrivals')->name('new-arrivals');
    Route::get('/best-sellers', 'bestSellers')->name('best-sellers');
});

Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product');
Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('page');
Route::get('/faqs', [PageController::class, 'faqs'])->name('faqs');
