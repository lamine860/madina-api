<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\CategoryController;
use Modules\Catalog\Http\Controllers\ProductController;

Route::prefix('v1')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

    Route::get('shops/{shop:slug}/products', [ProductController::class, 'index'])->name('shops.products.index');

    Route::get('shops/{shop:slug}/products/{product:slug}', [ProductController::class, 'show'])
        ->scopeBindings()
        ->name('shops.products.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('shops/{shop}/products', [ProductController::class, 'store'])->name('shops.products.store');
        Route::put('shops/{shop}/products/{product:id}', [ProductController::class, 'update'])
            ->scopeBindings()
            ->name('shops.products.update');
    });
});
