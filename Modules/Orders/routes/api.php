<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderController;
use Modules\Orders\Http\Controllers\ShopOrderController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])
        ->whereNumber('order')
        ->name('orders.show');
    Route::post('orders/checkout', [OrderController::class, 'checkout'])->name('orders.checkout');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->whereNumber('order')
        ->name('orders.status.update');

    Route::get('shops/{shop}/orders', [ShopOrderController::class, 'index'])->name('shops.orders.index');
});
