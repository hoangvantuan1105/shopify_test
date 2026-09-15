<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShopifyAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('products.index');
});

// OAuth Routes
Route::get('/auth', [ShopifyAuthController::class, 'auth'])->name('shopify.auth');
Route::get('/auth/callback', [ShopifyAuthController::class, 'callback'])->name('shopify.callback');

// Products Management & Sync
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::post('/products/sync', [ProductController::class, 'sync'])->name('products.sync');
Route::post('/products/embed', [ProductController::class, 'embedAll'])->name('products.embed');

// Semantic Search
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
