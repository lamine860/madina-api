<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reviews\Http\Controllers\OrderReviewController;
use Modules\Reviews\Http\Controllers\ProductReviewController;
use Modules\Reviews\Http\Controllers\ReviewManagementController;

Route::prefix('v1')->group(function (): void {
    Route::get('shops/{shop:slug}/products/{product:slug}/reviews', [ProductReviewController::class, 'index'])
        ->scopeBindings()
        ->name('shops.products.reviews.index');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('orders/{order}/reviewable-items', [OrderReviewController::class, 'reviewableItems'])
            ->whereNumber('order')
            ->name('orders.reviewable-items.index');

        Route::post('orders/{order}/items/{orderItem}/reviews', [OrderReviewController::class, 'store'])
            ->whereNumber('order')
            ->whereNumber('orderItem')
            ->name('orders.items.reviews.store');

        Route::patch('reviews/{review}', [ReviewManagementController::class, 'update'])
            ->whereNumber('review')
            ->name('reviews.update');

        Route::delete('reviews/{review}', [ReviewManagementController::class, 'destroy'])
            ->whereNumber('review')
            ->name('reviews.destroy');
    });
});
