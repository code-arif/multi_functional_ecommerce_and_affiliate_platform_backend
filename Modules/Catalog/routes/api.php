<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\BrandManageController;
use Modules\Catalog\Http\Controllers\CategoryController;
use Modules\Catalog\Http\Controllers\CategoryManageController;
/*
|--------------------------------------------------------------------------
| Catalog Module API Routes
| Prefix: api/v1
|--------------------------------------------------------------------------
*/

// V1 block
Route::prefix('v1')->group(function () {

    // Categories
    Route::prefix('categories')->middleware('throttle:api')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('{slug}', [CategoryController::class, 'show']);
    });


    Route::middleware(['auth:sanctum', 'admin', 'banned'])->prefix('admin')->group(function () {
        // Categories Management
        Route::get('categories', [CategoryManageController::class, 'index'])->middleware('permission:categories.view,categories.manage'); // DONE: Category list & search (Admin CategoriesPage, ProductFormPage)
        Route::get('categories/{category:uuid}', [CategoryManageController::class, 'show'])->middleware('permission:categories.view,categories.manage'); // DONE: Category details & products (Admin CategoryDetailPage)
        Route::post('categories/store', [CategoryManageController::class, 'store'])->middleware('permission:categories.create,categories.manage'); // DONE: Create category (Admin CategoryFormModal)
        Route::put('categories/{category:uuid}/update', [CategoryManageController::class, 'update'])->middleware('permission:categories.edit,categories.manage'); // DONE: Update category (Admin CategoryFormModal)
        Route::post('categories/{category:uuid}/approve', [CategoryManageController::class, 'approve'])->middleware('permission:categories.edit,categories.manage'); // DONE: Approve category proposal (Admin CategoriesPage, CategoryDetailPage)
        Route::post('categories/{category:uuid}/reject', [CategoryManageController::class, 'reject'])->middleware('permission:categories.edit,categories.manage'); // DONE: Reject category proposal (Admin CategoriesPage, CategoryDetailPage)
        Route::delete('categories/{category:uuid}/delete', [CategoryManageController::class, 'destroy'])->middleware('permission:categories.manage'); // DONE: Delete category (Admin CategoriesPage, CategoryDetailPage)

        // Brands
        Route::get('brands', [BrandManageController::class, 'index'])->middleware('permission:brands.view,brands.manage'); // DONE: Brand list, search & filter (Admin BrandsPage, ProductFormPage)
        Route::post('brands/store', [BrandManageController::class, 'store'])->middleware('permission:brands.create,brands.manage'); // DONE: Create brand (Admin BrandsPage)
        Route::put('brands/{brand:uuid}/update', [BrandManageController::class, 'update'])->middleware('permission:brands.edit,brands.manage'); // DONE: Update brand (Admin BrandsPage)
        Route::delete('brands/{brand:uuid}/delete', [BrandManageController::class, 'destroy'])->middleware('permission:brands.manage'); // DONE: Delete brand (Admin BrandsPage)
    });

    // Vendor block (active vendors can browse categories/brands and submit pending proposals)
    Route::middleware(['auth:sanctum', 'vendor', 'banned'])->prefix('vendor')->group(function () {
        Route::get('categories', [CategoryManageController::class, 'index']); // DONE: Vendor category list & search (Vendor CategoriesPage, ProductFormPage)
        Route::post('categories/store', [CategoryManageController::class, 'store']); // DONE: Vendor category proposal (Vendor CategoriesPage)

        Route::get('brands', [BrandManageController::class, 'index']); // DONE: Vendor brand list & search (Vendor BrandsPage, ProductFormPage)
        Route::post('brands/store', [BrandManageController::class, 'store']); // DONE: Vendor brand proposal (Vendor BrandsPage)
    });
});
