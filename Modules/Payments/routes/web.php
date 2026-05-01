<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\PaymentController;

Route::get('payments/lengopay/success', [PaymentController::class, 'success'])
    ->name('payments.lengopay.success');

Route::get('payments/lengopay/cancel', [PaymentController::class, 'cancel'])
    ->name('payments.lengopay.cancel');
