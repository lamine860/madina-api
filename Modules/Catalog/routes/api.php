<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\CategoryController;
use Modules\Catalog\Http\Controllers\ProductController;
use Modules\Catalog\Http\Controllers\ProductImageController;

Route::prefix('v1')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

    Route::get('shops/{shop:slug}/products', [ProductController::class, 'index'])->name('shops.products.index');

    Route::get('shops/{shop:slug}/products/{product:slug}', [ProductController::class, 'show'])
        ->scopeBindings()
        ->name('shops.products.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category:slug}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category:slug}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('shops/{shop}/products', [ProductController::class, 'store'])->name('shops.products.store');
        Route::put('shops/{shop}/products/{product:id}', [ProductController::class, 'update'])
            ->scopeBindings()
            ->name('shops.products.update');
        Route::delete('shops/{shop}/products/{product:id}', [ProductController::class, 'destroy'])
            ->scopeBindings()
            ->name('shops.products.destroy');

        Route::post('shops/{shop}/products/{product:id}/images', [ProductImageController::class, 'store'])
            ->scopeBindings()
            ->name('shops.products.images.store');
        Route::patch('shops/{shop}/products/{product:id}/images', [ProductImageController::class, 'update'])
            ->scopeBindings()
            ->name('shops.products.images.update');
        Route::delete('shops/{shop}/products/{product:id}/images/{product_image}', [ProductImageController::class, 'destroy'])
            ->scopeBindings()
            ->name('shops.products.images.destroy');
    });
});
