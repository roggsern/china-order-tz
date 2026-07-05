<?php

use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductImageController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'CHINA ORDER TZ API',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:admin-login');

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/trash', [AdminProductController::class, 'trash']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{product}', [AdminProductController::class, 'show']);
    Route::get('/products/{product}/images', [AdminProductController::class, 'indexImages']);
    Route::post('/products/{product}/images', [AdminProductController::class, 'storeImage']);
    Route::patch('/products/{product}/stock', [AdminProductController::class, 'updateStock']);
    Route::get('/products/{product}/inventory/movements', [AdminProductController::class, 'indexInventoryMovements']);
    Route::put('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
    Route::post('/products/{id}/restore', [AdminProductController::class, 'restore']);
    Route::delete('/products/{id}/force', [AdminProductController::class, 'forceDestroy']);
    Route::delete('/product-images/{image}', [AdminProductImageController::class, 'destroy']);
    Route::patch('/product-images/{image}/primary', [AdminProductImageController::class, 'setPrimary']);
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::get('/brands', [AdminBrandController::class, 'index']);
    Route::post('/brands', [AdminBrandController::class, 'store']);
    Route::get('/brands/{brand}', [AdminBrandController::class, 'show']);
    Route::put('/brands/{brand}', [AdminBrandController::class, 'update']);
    Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::post('/orders', [AdminOrderController::class, 'store']);
    Route::patch('/orders/{order}/pay', [AdminOrderController::class, 'pay']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
