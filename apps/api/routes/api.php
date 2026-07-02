<?php

use App\Http\Controllers\Admin\AdminProductController;
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
    Route::put('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
    Route::post('/products/{id}/restore', [AdminProductController::class, 'restore']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
