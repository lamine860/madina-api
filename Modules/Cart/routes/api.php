<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\CartController;
use Modules\Cart\Http\Controllers\WishlistController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('cart/items/{cart_item}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('cart/items/{cart_item}', [CartController::class, 'destroy'])->name('cart.items.destroy');

    Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('wishlist/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
});
