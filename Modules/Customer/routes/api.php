<?php

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\CustomerController;

/*
|--------------------------------------------------------------------------
| Customer Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin', 'banned'])
    ->prefix('v1/admin')
    ->group(function () {
        // Canonical Customers endpoints
        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->middleware('permission:users.view');
            Route::get('stats', [CustomerController::class, 'stats'])->middleware('permission:users.view');
            Route::post('/', [CustomerController::class, 'store'])->middleware('permission:users.create');
            Route::get('{customer}', [CustomerController::class, 'show'])->middleware('permission:users.view');
            Route::put('{customer}', [CustomerController::class, 'update'])->middleware('permission:users.edit');
            Route::patch('{customer}/status', [CustomerController::class, 'updateStatus'])->middleware('permission:users.ban');
            Route::delete('{customer}', [CustomerController::class, 'destroy'])->middleware('permission:users.delete');
        });

        // Transferred legacy /admin/users endpoints (kept compatible)
        Route::prefix('users')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->middleware('permission:users.view');
            Route::get('stats', [CustomerController::class, 'stats'])->middleware('permission:users.view');
            Route::post('/', [CustomerController::class, 'store'])->middleware('permission:users.create');
            Route::get('{customer}', [CustomerController::class, 'show'])->middleware('permission:users.view');
            Route::put('{customer}', [CustomerController::class, 'update'])->middleware('permission:users.edit');
            Route::patch('{customer}/status', [CustomerController::class, 'updateStatus'])->middleware('permission:users.ban');
            Route::delete('{customer}', [CustomerController::class, 'destroy'])->middleware('permission:users.delete');
        });
    });
