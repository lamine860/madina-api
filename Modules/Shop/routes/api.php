<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Shop\Http\Controllers\ShopController;

Route::prefix('v1')->group(function (): void {
    Route::get('shops/{shop:slug}', [ShopController::class, 'show'])->name('shops.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('shops', [ShopController::class, 'store'])->name('shops.store');
        Route::put('shops/{shop}', [ShopController::class, 'update'])->name('shops.update');
        // destroy : binding implicite Shop par id (exclut les enregistrements soft-supprimés).
        Route::delete('shops/{shop}', [ShopController::class, 'destroy'])->name('shops.destroy');
    });
});
