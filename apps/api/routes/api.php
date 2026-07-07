<?php

use App\Http\Controllers\Admin\AdminShipmentController;
use App\Http\Controllers\Admin\AdminSimulateNmbCallbackController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCartController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminMockPaymentController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductImageController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhooks\NmbWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'CHINA ORDER TZ API',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/webhooks/payments/nmb', [NmbWebhookController::class, 'receive']);

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:customer-register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:customer-login');

Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:admin-login');

Route::middleware(['auth:sanctum', 'ensure.user', 'user.active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
    Route::get('/orders/{order}/tracking', [CustomerOrderController::class, 'tracking']);
    Route::get('/cart', [AdminCartController::class, 'index']);
    Route::post('/cart/items', [AdminCartController::class, 'store']);
    Route::patch('/cart/items/{item}', [AdminCartController::class, 'update']);
    Route::delete('/cart/items/{item}', [AdminCartController::class, 'destroyItem']);
    Route::delete('/cart', [AdminCartController::class, 'destroy']);
    Route::post('/cart/checkout', [AdminCartController::class, 'checkout']);
    Route::post('/payments/{payment}/initiate', [PaymentController::class, 'initiate']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/profile/address', [ProfileController::class, 'showAddress']);
    Route::patch('/profile/address', [ProfileController::class, 'updateAddress']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'ensure.admin', 'admin.active'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
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
    Route::patch('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);
    Route::patch('/orders/{order}/shipment-status', [AdminShipmentController::class, 'update']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::get('/payments', [AdminPaymentController::class, 'index']);
    Route::post('/payments', [AdminPaymentController::class, 'store']);
    Route::post('/payments/{payment}/mock', [AdminMockPaymentController::class, 'process']);
    Route::post('/payments/{payment}/simulate-nmb-callback', [AdminSimulateNmbCallbackController::class, 'store']);
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show']);
    Route::put('/payments/{payment}', [AdminPaymentController::class, 'update']);
    Route::delete('/payments/{payment}', [AdminPaymentController::class, 'destroy']);
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
});
