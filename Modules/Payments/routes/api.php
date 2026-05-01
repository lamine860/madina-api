<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\PaymentController;

Route::prefix('v1')->group(function (): void {
    Route::post('payments/lengopay/webhook', [PaymentController::class, 'handleWebhook'])
        ->name('payments.lengopay.webhook');
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('orders/{order}/payments/lengopay/initiate', [PaymentController::class, 'initiate'])
        ->whereNumber('order')
        ->name('orders.payments.lengopay.initiate');
});
