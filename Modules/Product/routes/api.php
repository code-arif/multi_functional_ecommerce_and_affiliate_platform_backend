<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\NewProductController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Vendor\Http\Controllers\VendorProductController;

Route::middleware(['auth:sanctum', 'admin', 'banned'])->prefix('admin')->group(function () {
    Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view'); // DONE: Admin product list & filters (ProductsPage, ProductFormPage)
    Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create'); // DONE: Admin create product (ProductFormPage)
    Route::post('products/upload-image', [ProductController::class, 'uploadImage'])->middleware('permission:products.create'); // DONE: Upload product image/thumbnail (ProductFormPage)
    Route::post('products/upload-video', [ProductController::class, 'uploadVideo'])->middleware('permission:products.create'); // DONE: Upload product video/poster (ProductFormPage)
    Route::get('products/{product:uuid}', [ProductController::class, 'show'])->middleware('permission:products.view'); // DONE: Admin product details (ProductDetail, ProductFormPage)
    Route::put('products/{product:uuid}', [ProductController::class, 'update'])->middleware('permission:products.edit'); // DONE: Admin update product (ProductFormPage)
    Route::patch('products/{product:uuid}/status', [ProductController::class, 'updateStatus'])->middleware('permission:products.edit'); // DONE: Admin quick status update (ProductsPage)
    Route::delete('products/{product:uuid}', [ProductController::class, 'destroy'])->middleware('permission:products.delete'); // DONE: Admin delete product (ProductsPage)
});

// Vendor products & video upload
Route::middleware(['auth:sanctum', 'vendor', 'banned'])->prefix('vendor')->group(function () {
    Route::get('products', [VendorProductController::class, 'index']); // DONE: Vendor product list (ProductsPage, ProductFormPage)
    Route::post('products', [VendorProductController::class, 'store']); // DONE: Vendor create/link product with videos (ProductFormPage)
    Route::post('products/upload-image', [VendorProductController::class, 'uploadImage']); // DONE: Vendor upload gallery image/thumbnail (ProductFormPage)
    Route::post('products/upload-video', [VendorProductController::class, 'uploadVideo']); // DONE: Vendor upload product video/poster (ProductFormPage)
    Route::get('products/{product:uuid}', [VendorProductController::class, 'show']); // DONE: Vendor product details (ProductDetailPage, ProductFormPage)
    Route::put('products/{product:uuid}', [VendorProductController::class, 'update']); // DONE: Vendor update product pricing (ProductFormPage)
    Route::delete('products/{product:uuid}', [VendorProductController::class, 'destroy']); // DONE: Vendor remove product (ProductsPage)
});

// Products — public browsing
Route::prefix('products')->middleware('throttle:api')->group(function () {
    Route::get('/',                     [NewProductController::class, 'index']);
    Route::get('featured',              [NewProductController::class, 'featured']);
    Route::get('new-arrivals',          [NewProductController::class, 'newArrivals']);
    Route::get('bestsellers',           [NewProductController::class, 'bestsellers']);
    // Route::get('{slug}/reviews/stats',  [ReviewController::class, 'stats']);
    // Route::get('{slug}/reviews',        [ReviewController::class, 'index']);
    Route::get('{slug}/related',        [NewProductController::class, 'related']);
    Route::get('{slug}',                [NewProductController::class, 'show']);
});

