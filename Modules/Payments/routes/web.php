<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\PaymentController;

Route::get('payments/lengopay/success', [PaymentController::class, 'success'])
    ->name('payments.lengopay.success');

Route::get('payments/lengopay/cancel', [PaymentController::class, 'cancel'])
    ->name('payments.lengopay.cancel');

Route::get('payments/orange/success', [PaymentController::class, 'orangeSuccess'])
    ->name('payments.orange.success');

Route::get('payments/orange/cancel', [PaymentController::class, 'orangeCancel'])
    ->name('payments.orange.cancel');
