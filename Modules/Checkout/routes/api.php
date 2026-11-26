<?php

use Illuminate\Support\Facades\Route;
use Modules\Checkout\Http\Controllers\CheckoutController;

/*
|--------------------------------------------------------------------------
| Checkout Module API Routes
| Prefix: api/v1
|--------------------------------------------------------------------------
*/

// Checkout — authenticated routes (stricter throttle on order placement)
Route::middleware(['auth:sanctum', 'banned'])->prefix('checkout')->group(function () {
    Route::post('/',                [CheckoutController::class, 'process'])->middleware('throttle:checkout');
    Route::get('shipping-cost',     [CheckoutController::class, 'shippingCost']);
    Route::post('preview',          [CheckoutController::class, 'preview']);
    Route::get('shipping-options',  [CheckoutController::class, 'shippingOptions']);
    Route::post('calculate-tax',    [CheckoutController::class, 'calculateTax']);
});
