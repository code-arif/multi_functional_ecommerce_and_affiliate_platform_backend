<?php

use Illuminate\Support\Facades\Route;
use Modules\Vendor\Http\Controllers\Admin\VendorManageController;
use Modules\Vendor\Http\Controllers\VendorAddressController;
use Modules\Vendor\Http\Controllers\VendorBankAccountController;
use Modules\Vendor\Http\Controllers\VendorController;
use Modules\Vendor\Http\Controllers\VendorDashboardController;
use Modules\Vendor\Http\Controllers\VendorSettingsController;
use Modules\Vendor\Http\Controllers\VendorStaffController;

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
        Route::get('dashboard', [VendorDashboardController::class, 'index']); // DONE: vendor dashboard stats

        Route::get('profile', [VendorController::class, 'profile']); // DONE: get vendor profile
        Route::put('profile', [VendorController::class, 'updateProfile']); // DONE: update shop profile

        Route::get('documents', [VendorController::class, 'documents']); // DONE: list KYC documents
        Route::post('documents', [VendorController::class, 'uploadDocument']); // DONE: upload KYC document
        Route::delete('documents/{document}', [VendorController::class, 'destroyDocument']); // DONE: delete KYC document

        Route::get('settings', [VendorSettingsController::class, 'index']); // DONE: get vendor settings
        Route::post('settings', [VendorSettingsController::class, 'update']); // DONE: update vendor settings

        Route::get('bank-accounts', [VendorBankAccountController::class, 'index']); // DONE: list payout accounts
        Route::post('bank-accounts', [VendorBankAccountController::class, 'store']); // DONE: add payout account
        Route::put('bank-accounts/{bankAccount}', [VendorBankAccountController::class, 'update']); // DONE: update payout account
        Route::post('bank-accounts/{bankAccount}/default', [VendorBankAccountController::class, 'setDefault']); // DONE: set default payout account
        Route::delete('bank-accounts/{bankAccount}', [VendorBankAccountController::class, 'destroy']); // DONE: delete payout account

        Route::get('addresses', [VendorAddressController::class, 'index']); // DONE: list shop addresses
        Route::post('addresses', [VendorAddressController::class, 'store']); // DONE: add shop address
        Route::put('addresses/{address}', [VendorAddressController::class, 'update']); // DONE: update shop address
        Route::post('addresses/{address}/default', [VendorAddressController::class, 'setDefault']); // DONE: set default shop address
        Route::delete('addresses/{address}', [VendorAddressController::class, 'destroy']); // DONE: delete shop address

        Route::get('staff', [VendorStaffController::class, 'index']); // DONE: list shop staff
        Route::post('staff', [VendorStaffController::class, 'store']); // DONE: invite/add shop staff
        Route::put('staff/{staff}', [VendorStaffController::class, 'update']); // DONE: update staff role
        Route::post('staff/{staff}/toggle-status', [VendorStaffController::class, 'toggleStatus']); // DONE: toggle staff status
        Route::delete('staff/{staff}', [VendorStaffController::class, 'destroy']); // DONE: remove shop staff
    });

    // Admin-Facing Vendor Moderation & Management
    Route::middleware(['auth:sanctum', 'admin', 'banned'])->prefix('admin')->group(function () {
        Route::prefix('vendors')->middleware('permission:vendors.view')->group(function () {
            Route::get('/', [VendorManageController::class, 'index']); // DONE: list vendors for admin
            Route::get('stats', [VendorManageController::class, 'stats']); // DONE: vendor stats
            Route::get('pending', [VendorManageController::class, 'pending']); // DONE: pending vendors
            Route::get('trashed', [VendorManageController::class, 'trashed']); // DONE: trashed vendors
            Route::get('documents', [VendorManageController::class, 'documents']); // DONE: vendor documents
            // Route::post('/', [VendorManageController::class, 'store'])->middleware('permission:vendors.create'); // DONE: create vendor
            Route::post('/store', [VendorManageController::class, 'store'])->middleware('permission:vendors.create'); // DONE: create vendor
            Route::get('{uuid}', [VendorManageController::class, 'show']); // DONE: Vendor details
            Route::delete('{uuid}', [VendorManageController::class, 'destroy'])->middleware('permission:vendors.manage'); // DONE: vendor delete
            Route::post('{uuid}/restore', [VendorManageController::class, 'restore'])->middleware('permission:vendors.manage'); // DONE: vendor restore
            Route::delete('{uuid}/force', [VendorManageController::class, 'forceDelete'])->middleware('permission:vendors.manage'); // DONE: vendor force delete
            Route::put('{uuid}/commission', [VendorManageController::class, 'updateCommission'])->middleware('permission:vendors.manage'); // DONE: vendor commission update
            Route::post('{uuid}/approve', [VendorManageController::class, 'approve'])->middleware('permission:vendors.approve'); // DONE: approve vendor
            Route::post('{uuid}/reject', [VendorManageController::class, 'reject'])->middleware('permission:vendors.approve'); // DONE: reject vendor
            Route::post('{uuid}/suspend', [VendorManageController::class, 'suspend'])->middleware('permission:vendors.manage'); // DONE: suspend vendor
            Route::post('{uuid}/pending', [VendorManageController::class, 'makePending'])->middleware('permission:vendors.manage'); // DONE: make pending vendor
            Route::post('documents/{document}/verify', [VendorManageController::class, 'verifyDocument'])->middleware('permission:vendors.manage'); // DONE: verify vendor document
            Route::post('documents/{document}/reject', [VendorManageController::class, 'rejectDocument'])->middleware('permission:vendors.manage'); // DONE: reject vendor document
        });
    });
});
