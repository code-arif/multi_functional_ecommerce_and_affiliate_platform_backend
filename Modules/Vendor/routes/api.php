<?php

use Illuminate\Support\Facades\Route;
use Modules\Vendor\Http\Controllers\Admin\VendorManageController;
use Modules\Vendor\Http\Controllers\VendorController;

/*
|--------------------------------------------------------------------------
| Vendor Module API Routes
| Prefix: api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Public Storefront Routes
    Route::prefix('vendors')->middleware('throttle:api')->group(function () {
        Route::get('/', [VendorController::class, 'index']);
        Route::get('{slug}', [VendorController::class, 'show']);
    });

    // Customer Upgrade: Apply to Become a Vendor
    Route::middleware(['auth:sanctum', 'banned'])->prefix('vendor')->group(function () {
        Route::post('apply', [VendorController::class, 'apply']); // DONE: apply as vendor
    });

    // Authenticated Vendor Management (Scoped to Active Vendor)
    Route::middleware(['auth:sanctum', 'vendor', 'banned'])->prefix('vendor')->group(function () {
        Route::get('profile', [VendorController::class, 'profile']);
        Route::put('profile', [VendorController::class, 'updateProfile']);
        Route::post('documents', [VendorController::class, 'uploadDocument']);
        Route::get('documents', [VendorController::class, 'documents']);
    });

    // Admin-Facing Vendor Moderation & Management
    Route::middleware(['auth:sanctum', 'admin', 'banned'])->prefix('admin')->group(function () {
        Route::prefix('vendors')->middleware('permission:vendors.view')->group(function () {
            Route::get('/', [VendorManageController::class, 'index']); // DONE: list vendors for admin
            // Route::post('/', [VendorManageController::class, 'store'])->middleware('permission:vendors.create'); // DONE: create vendor
            Route::post('/store', [VendorManageController::class, 'store'])->middleware('permission:vendors.create'); // DONE: create vendor
            Route::get('{uuid}', [VendorManageController::class, 'show']); // DONE: Vendor details
            Route::post('{uuid}/approve', [VendorManageController::class, 'approve'])->middleware('permission:vendors.approve');
            Route::post('{uuid}/reject', [VendorManageController::class, 'reject'])->middleware('permission:vendors.approve');
            Route::post('{uuid}/suspend', [VendorManageController::class, 'suspend'])->middleware('permission:vendors.manage');
            Route::post('{uuid}/pending', [VendorManageController::class, 'makePending'])->middleware('permission:vendors.manage');
            Route::post('documents/{document}/verify', [VendorManageController::class, 'verifyDocument'])->middleware('permission:vendors.manage');
            Route::post('documents/{document}/reject', [VendorManageController::class, 'rejectDocument'])->middleware('permission:vendors.manage');
        });
    });
});
