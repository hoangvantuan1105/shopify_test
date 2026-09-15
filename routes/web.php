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

// Shopify Webhooks (Real-time synchronization)
Route::prefix('webhooks')->middleware(\App\Http\Middleware\VerifyShopifyWebhook::class)->group(function () {
    Route::post('/products/create', [\App\Http\Controllers\ShopifyWebhookController::class, 'handleProductCreated'])->name('webhooks.products.create');
    Route::post('/products/update', [\App\Http\Controllers\ShopifyWebhookController::class, 'handleProductUpdated'])->name('webhooks.products.update');
    Route::post('/products/delete', [\App\Http\Controllers\ShopifyWebhookController::class, 'handleProductDeleted'])->name('webhooks.products.delete');
});
