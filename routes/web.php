<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopifyAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('products.index');
});

Route::get('/auth', [ShopifyAuthController::class, 'auth'])->name('shopify.auth');
Route::get('/auth/callback', [ShopifyAuthController::class, 'callback'])->name('shopify.callback');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::post('/products/sync', [ProductController::class, 'sync'])->name('products.sync');
