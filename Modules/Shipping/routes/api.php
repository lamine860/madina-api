<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Shipping\Http\Controllers\ShipmentVerificationController;
use Modules\Shipping\Http\Controllers\ShippingOptionsController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('shipping/options', [ShippingOptionsController::class, 'index'])->name('shipping.options');
    Route::post('shipments/verify-pickup', [ShipmentVerificationController::class, 'verifyPickup'])->name('shipments.verify-pickup');
    Route::post('shipments/verify-delivery', [ShipmentVerificationController::class, 'verifyDelivery'])->name('shipments.verify-delivery');
});
