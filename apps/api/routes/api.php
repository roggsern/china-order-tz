<?php

use App\Http\Controllers\Admin\AdminProductTypeController;
use App\Http\Controllers\Admin\AdminShipmentController;
use App\Http\Controllers\Admin\AdminSimulateNmbCallbackController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCatalogAttributeController;
use App\Http\Controllers\Admin\AdminCatalogProductTypeController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminDepartmentController;
use App\Http\Controllers\Admin\AdminDeliveryOptionController;
use App\Http\Controllers\Admin\AdminFulfillmentController;
use App\Http\Controllers\Admin\AdminWarehouseController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminNotificationTemplateController;
use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminCommerceChannelController;
use App\Http\Controllers\Admin\AdminSupplierController;
use App\Http\Controllers\Admin\AdminProfitController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminCustomerTagController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminLoyaltyController;
use App\Http\Controllers\Admin\AdminInventoryController;
use App\Http\Controllers\Admin\AdminGrowthController;
use App\Http\Controllers\Admin\AdminCmsHomepageController;
use App\Http\Controllers\Admin\AdminCmsHeroSlideController;
use App\Http\Controllers\Admin\AdminCmsFeaturedContentController;
use App\Http\Controllers\Admin\AdminCmsCampaignController;
use App\Http\Controllers\Admin\AdminCmsNavigationController;
use App\Http\Controllers\Storefront\StorefrontCmsHomepageController;
use App\Http\Controllers\Storefront\StorefrontCmsNavigationController;
use App\Http\Controllers\CustomerLoyaltyController;
use App\Http\Controllers\CustomerGrowthController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminStoreAssignmentController;
use App\Http\Controllers\Admin\AdminPosController;
use App\Http\Controllers\Admin\AdminPosPaymentMethodController;
use App\Http\Controllers\Admin\AdminPosReturnController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\Admin\AdminPurchaseOrderController;
use App\Http\Controllers\Admin\AdminShipmentsController;
use App\Http\Controllers\Admin\AdminShipmentTrackingController;
use App\Http\Controllers\Admin\AdminSubcategoryController;
use App\Http\Controllers\Admin\AdminMockPaymentController;
use App\Http\Controllers\Admin\AdminChinaWorkflowController;
use App\Http\Controllers\Admin\AdminCustomerAgentController;
use App\Http\Controllers\Admin\AdminOrderTimelineController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductImageController;
use App\Http\Controllers\Admin\AdminProductAttributeController;
use App\Http\Controllers\Admin\AdminProductMediaController;
use App\Http\Controllers\Admin\AdminProductShippingOptionController;
use App\Http\Controllers\Admin\AdminProductVariantController;
use App\Http\Controllers\Admin\AdminVariantPriceController;
use App\Http\Controllers\Admin\AdminVariantInventoryController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\StorefrontStoreController;
use App\Http\Controllers\Storefront\TzStorefrontController;
use App\Http\Controllers\Storefront\ChinaStorefrontController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\CustomerReturnController;
use App\Http\Controllers\CustomerProductController;
use App\Http\Controllers\Admin\AdminReturnController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryOptionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NmbPaymentCallbackController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentOrchestratorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhooks\NmbWebhookController;
use App\Support\Ops\OperationalHealth;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $includeDiagnostics = ! app()->environment('production');
    $probe = OperationalHealth::probe($includeDiagnostics);
    $criticalOnly = app()->environment('production')
        && (bool) config('monitoring.health.critical_only', false);

    $payload = [
        'status' => $probe['status'],
        'service' => 'CHINA ORDER TZ API',
        'critical_ok' => (bool) ($probe['critical_ok'] ?? false),
        'timestamp' => now()->toIso8601String(),
    ];

    // Uptime monitors: optional critical-only body in production.
    if (! $criticalOnly) {
        $payload['checks'] = $probe['checks'];
    }

    // RC1-G4B/G4C1 — withhold environment/debug/driver internals in production.
    if ($includeDiagnostics) {
        $payload['environment'] = app()->environment();
        $payload['debug'] = (bool) config('app.debug');
        if (isset($probe['details'])) {
            $payload['details'] = $probe['details'];
        }
    }

    // 503 only when critical probes fail (database/storage). Soft degradation stays 200.
    $httpOk = ($probe['critical_ok'] ?? false) === true;

    return response()->json($payload, $httpOk ? 200 : 503);
});

Route::post('/webhooks/nmb', [NmbWebhookController::class, 'receive'])
    ->middleware('throttle:webhooks');

Route::post('/payments/nmb/callback', NmbPaymentCallbackController::class)
    ->middleware('throttle:webhooks');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:customer-register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:customer-login');

Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:admin-login');

Route::get('/products', [CustomerProductController::class, 'index']);
Route::get('/products/{product:slug}', [CustomerProductController::class, 'show']);
Route::get('/products/{product:slug}/configuration', [CustomerProductController::class, 'configuration']);
Route::post('/products/{product:slug}/quote', [CustomerProductController::class, 'quote'])
    ->middleware('throttle:60,1');
Route::get('/categories', [CustomerProductController::class, 'categories']);
Route::get('/categories/{slug}', [CustomerProductController::class, 'showCategory']);
Route::get('/brands', [CustomerProductController::class, 'brands']);

Route::get('/stores', [StorefrontStoreController::class, 'index']);
Route::get('/stores/{slug}', [StorefrontStoreController::class, 'show']);

Route::prefix('storefront/tz')->group(function () {
    Route::get('/stores', [TzStorefrontController::class, 'stores']);
    Route::get('/stores/{store}', [TzStorefrontController::class, 'showStore']);
    Route::get('/stores/{store}/categories', [TzStorefrontController::class, 'categories']);
    Route::get('/stores/{store}/products', [TzStorefrontController::class, 'products']);
    Route::get('/stores/{store}/products/{product}', [TzStorefrontController::class, 'showProduct']);
});

Route::prefix('storefront/china')->group(function () {
    Route::get('/menu', [ChinaStorefrontController::class, 'menu']);
    Route::get('/categories', [ChinaStorefrontController::class, 'categories']);
    Route::get('/brands', [ChinaStorefrontController::class, 'brands']);
    Route::get('/products', [ChinaStorefrontController::class, 'products']);
});

Route::get('/storefront/homepage', [StorefrontCmsHomepageController::class, 'show']);
Route::get('/storefront/navigation', [StorefrontCmsNavigationController::class, 'show']);

Route::middleware(['auth:sanctum', 'ensure.user', 'user.active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::post('/orders/confirm', [CustomerOrderController::class, 'confirm'])
        ->middleware('throttle:checkout');
    Route::post('/orders/from-checkout/{checkoutSession}', [CustomerOrderController::class, 'fromCheckout'])
        ->middleware('throttle:checkout');
    Route::post('/orders/{order}/payments', [CustomerOrderController::class, 'storePayment'])
        ->middleware('throttle:payments');
    Route::get('/orders/{order}/payment', [CustomerOrderController::class, 'showPayment']);
    Route::get('/orders/{order}/delivery-option', [DeliveryOptionController::class, 'show']);
    Route::post('/orders/{order}/delivery-option', [DeliveryOptionController::class, 'store'])
        ->middleware('throttle:checkout');
    Route::patch('/orders/{order}/delivery-option', [DeliveryOptionController::class, 'update'])
        ->middleware('throttle:checkout');
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);
    Route::get('/orders/{order}/tracking', [CustomerOrderController::class, 'tracking']);
    Route::post('/orders/{order}/returns', [CustomerReturnController::class, 'store'])
        ->middleware('throttle:returns');
    Route::get('/returns', [CustomerReturnController::class, 'index']);
    Route::get('/returns/{returnRequest}', [CustomerReturnController::class, 'show']);
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'store'])
        ->middleware('throttle:cart');
    Route::put('/cart/items/{item}', [CartController::class, 'update'])
        ->middleware('throttle:cart');
    Route::patch('/cart/items/{item}', [CartController::class, 'update'])
        ->middleware('throttle:cart');
    Route::delete('/cart/items/{item}', [CartController::class, 'destroyItem'])
        ->middleware('throttle:cart');
    Route::delete('/cart/clear', [CartController::class, 'destroy'])
        ->middleware('throttle:cart');
    Route::delete('/cart', [CartController::class, 'destroy'])
        ->middleware('throttle:cart');
    Route::post('/cart/buy-now', [CartController::class, 'buyNow'])
        ->middleware('throttle:cart');
    Route::get('/checkout', [CheckoutController::class, 'show']);
    Route::post('/checkout/prepare', [CheckoutController::class, 'prepare'])
        ->middleware('throttle:checkout');
    Route::post('/checkout/start', [CheckoutController::class, 'start'])
        ->middleware('throttle:checkout');
    Route::get('/checkout/{checkoutSession}', [CheckoutController::class, 'showSession']);
    Route::post('/checkout/{checkoutSession}/refresh', [CheckoutController::class, 'refresh'])
        ->middleware('throttle:checkout');
    Route::post('/checkout/{checkoutSession}/shipping-choice', [CheckoutController::class, 'applyShippingChoice'])
        ->middleware('throttle:checkout');
    Route::delete('/checkout/{checkoutSession}', [CheckoutController::class, 'destroySession']);
    Route::post('/promotions/validate', [PromotionController::class, 'validateCode']);
    Route::post('/promotions/apply', [PromotionController::class, 'apply']);
    Route::get('/loyalty/profile', [CustomerLoyaltyController::class, 'profile']);
    Route::get('/loyalty/transactions', [CustomerLoyaltyController::class, 'transactions']);
    Route::get('/loyalty/rewards', [CustomerLoyaltyController::class, 'rewards']);
    Route::post('/loyalty/redeem', [CustomerLoyaltyController::class, 'redeem']);
    Route::get('/loyalty/redemptions', [CustomerLoyaltyController::class, 'redemptions']);
    Route::get('/growth/offers', [CustomerGrowthController::class, 'offers']);
    Route::get('/growth/history', [CustomerGrowthController::class, 'history']);
    Route::post('/payments/start/{order}', [PaymentOrchestratorController::class, 'start'])
        ->middleware('throttle:payments');
    Route::get('/payments/{paymentTransaction}', [PaymentOrchestratorController::class, 'show']);
    Route::post('/payments/{paymentTransaction}/refresh', [PaymentOrchestratorController::class, 'refresh'])
        ->middleware('throttle:payments');
    Route::post('/payments/{payment}/initiate', [PaymentController::class, 'initiate'])
        ->middleware('throttle:payments');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/profile/address', [ProfileController::class, 'showAddress']);
    Route::patch('/profile/address', [ProfileController::class, 'updateAddress'])
        ->middleware('throttle:customer-profile');
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:customer-profile');
});

Route::middleware(['auth:sanctum', 'ensure.admin', 'admin.active'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    // Multi-store POS foundation
    Route::get('/stores', [AdminStoreController::class, 'index']);
    Route::post('/stores', [AdminStoreController::class, 'store']);
    Route::get('/stores/{store}', [AdminStoreController::class, 'show']);
    Route::put('/stores/{store}', [AdminStoreController::class, 'update']);
    Route::get('/stores/{store}/assignments', [AdminStoreAssignmentController::class, 'index']);
    Route::post('/stores/{store}/assignments', [AdminStoreAssignmentController::class, 'store']);
    Route::delete('/stores/{store}/assignments/{admin}', [AdminStoreAssignmentController::class, 'destroy']);

    Route::get('/pos/my-stores', [AdminPosController::class, 'myStores']);
    Route::get('/pos/dashboard', [AdminPosController::class, 'dashboard']);
    Route::get('/pos/sessions', [AdminPosController::class, 'listSessions']);
    Route::post('/pos/sessions/open', [AdminPosController::class, 'openSession']);
    Route::get('/pos/sessions/current', [AdminPosController::class, 'currentSession']);
    Route::post('/pos/sessions/close', [AdminPosController::class, 'closeSession']);
    Route::patch('/pos/sessions/float', [AdminPosController::class, 'updateFloat']);
    Route::get('/pos/sessions/{session}', [AdminPosController::class, 'showSession']);
    Route::get('/pos/catalog', [AdminPosController::class, 'catalog']);
    Route::post('/pos/quote', [AdminPosController::class, 'quote']);
    Route::get('/pos/payment-methods', [AdminPosController::class, 'paymentMethods']);
    Route::post('/pos/sales', [AdminPosController::class, 'completeSale']);
    Route::get('/pos/sales/{order}', [AdminPosController::class, 'showSale']);
    Route::get('/pos/orders/{order}/receipt', [AdminPosController::class, 'orderReceipt']);
    Route::get('/pos/receipts', [AdminPosController::class, 'listReceipts']);
    Route::get('/pos/receipts/{receipt}', [AdminPosController::class, 'showReceipt']);
    Route::get('/pos/receipts/{receipt}/preview', [AdminPosController::class, 'previewReceipt']);
    Route::post('/pos/receipts/{receipt}/print', [AdminPosController::class, 'printReceipt']);
    Route::post('/pos/receipts/{receipt}/reprint', [AdminPosController::class, 'reprintReceipt']);
    Route::get('/pos/receipts/{receipt}/pdf', [AdminPosController::class, 'downloadReceiptPdf']);

    Route::get('/pos/return-reasons', [AdminPosReturnController::class, 'reasons']);
    Route::get('/pos/returns/search', [AdminPosReturnController::class, 'search']);
    Route::get('/pos/returns/report', [AdminPosReturnController::class, 'report']);
    Route::get('/pos/returns', [AdminPosReturnController::class, 'index']);
    Route::post('/pos/returns', [AdminPosReturnController::class, 'store']);
    Route::get('/pos/returns/{returnRequest}', [AdminPosReturnController::class, 'show']);
    Route::get('/pos/orders/{order}/return-preview', [AdminPosReturnController::class, 'orderPreview']);
    Route::get('/pos/orders/{order}/returns', [AdminPosReturnController::class, 'orderReturns']);
    Route::get('/pos/loyalty/lookup', [AdminLoyaltyController::class, 'lookup']);
    Route::post('/pos/loyalty/{account}/redeem', [AdminLoyaltyController::class, 'redeem']);

    Route::get('/pos-payment-methods', [AdminPosPaymentMethodController::class, 'index']);
    Route::post('/pos-payment-methods', [AdminPosPaymentMethodController::class, 'store']);
    Route::put('/pos-payment-methods/{paymentMethod}', [AdminPosPaymentMethodController::class, 'update']);

    Route::get('/reports/sales', [AdminReportController::class, 'sales']);
    Route::get('/reports/orders', [AdminReportController::class, 'orders']);
    Route::get('/reports/payments', [AdminReportController::class, 'payments']);
    Route::get('/reports/warehouse', [AdminReportController::class, 'warehouse']);
    Route::get('/reports/shipments', [AdminReportController::class, 'shipments']);
    Route::get('/reports/returns', [AdminReportController::class, 'returns']);
    Route::get('/reports/notifications', [AdminReportController::class, 'notifications']);
    Route::get('/reports/{type}/export', [AdminReportController::class, 'export']);

    // Retail intelligence (read-only analytics over commerce engines)
    Route::get('/analytics/dashboard', [AdminAnalyticsController::class, 'dashboard']);
    Route::get('/analytics/sales', [AdminAnalyticsController::class, 'sales']);
    Route::get('/analytics/profit', [AdminAnalyticsController::class, 'profit']);
    Route::get('/analytics/payments', [AdminAnalyticsController::class, 'payments']);
    Route::get('/analytics/inventory', [AdminAnalyticsController::class, 'inventory']);
    Route::get('/analytics/returns', [AdminAnalyticsController::class, 'returns']);
    Route::get('/analytics/customers', [AdminAnalyticsController::class, 'customers']);
    Route::get('/analytics/promotions', [AdminAnalyticsController::class, 'promotions']);
    Route::get('/analytics/loyalty', [AdminAnalyticsController::class, 'loyalty']);
    Route::get('/analytics/growth', [AdminAnalyticsController::class, 'growth']);
    Route::get('/analytics/stores', [AdminAnalyticsController::class, 'stores']);
    Route::get('/analytics/sessions', [AdminAnalyticsController::class, 'sessions']);
    Route::get('/analytics/{type}/export', [AdminAnalyticsController::class, 'export']);
    Route::get('/commerce-channels', [AdminCommerceChannelController::class, 'index']);
    Route::get('/commerce-channels/{channel}', [AdminCommerceChannelController::class, 'show']);
    Route::get('/suppliers', [AdminSupplierController::class, 'index']);
    Route::post('/suppliers', [AdminSupplierController::class, 'store']);
    Route::get('/suppliers/{supplier}', [AdminSupplierController::class, 'show']);
    Route::put('/suppliers/{supplier}', [AdminSupplierController::class, 'update']);
    Route::post('/suppliers/{supplier}/products', [AdminSupplierController::class, 'storeProduct']);
    Route::get('/purchase-orders', [AdminPurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [AdminPurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{purchaseOrder}', [AdminPurchaseOrderController::class, 'show']);
    Route::patch('/purchase-orders/{purchaseOrder}/status', [AdminPurchaseOrderController::class, 'updateStatus']);
    Route::post('/purchase-orders/{purchaseOrder}/receive', [AdminPurchaseOrderController::class, 'receive']);
    Route::get('/orders/{order}/china-workflow', [AdminChinaWorkflowController::class, 'show']);
    Route::post('/orders/{order}/china-workflow/bootstrap', [AdminChinaWorkflowController::class, 'bootstrap']);
    Route::post('/purchase-orders/{purchaseOrder}/supplier-response', [AdminChinaWorkflowController::class, 'supplierResponse']);
    Route::post('/orders/{order}/china-workflow/qc', [AdminChinaWorkflowController::class, 'qc']);
    Route::post('/orders/{order}/china-workflow/consolidate', [AdminChinaWorkflowController::class, 'consolidate']);
    Route::post('/orders/{order}/china-workflow/export-ready', [AdminChinaWorkflowController::class, 'exportReady']);
    Route::post('/orders/{order}/china-workflow/agent-handoff', [AdminChinaWorkflowController::class, 'agentHandoff']);
    Route::get('/orders/{order}/customer-agent', [AdminCustomerAgentController::class, 'show']);
    Route::post('/orders/{order}/customer-agent/bootstrap', [AdminCustomerAgentController::class, 'bootstrap']);
    Route::post('/orders/{order}/customer-agent/authorize', [AdminCustomerAgentController::class, 'authorizePickup']);
    Route::post('/orders/{order}/customer-agent/reject', [AdminCustomerAgentController::class, 'reject']);
    Route::post('/orders/{order}/customer-agent/revoke', [AdminCustomerAgentController::class, 'revoke']);
    Route::post('/orders/{order}/customer-agent/schedule', [AdminCustomerAgentController::class, 'schedule']);
    Route::post('/orders/{order}/customer-agent/release', [AdminCustomerAgentController::class, 'release']);
    Route::post('/orders/{order}/customer-agent/arrive', [AdminCustomerAgentController::class, 'arrive']);
    Route::post('/orders/{order}/customer-agent/handover', [AdminCustomerAgentController::class, 'handover']);
    Route::get('/orders/{order}/timeline', [AdminOrderTimelineController::class, 'show']);
    Route::post('/orders/{order}/timeline/rebuild', [AdminOrderTimelineController::class, 'rebuild']);
    Route::get('/profits/dashboard', [AdminProfitController::class, 'dashboard']);
    Route::get('/profits/orders', [AdminProfitController::class, 'orders']);
    Route::get('/profits/products', [AdminProfitController::class, 'products']);
    Route::get('/profits/suppliers', [AdminProfitController::class, 'suppliers']);
    Route::get('/promotions', [AdminPromotionController::class, 'index']);
    Route::post('/promotions', [AdminPromotionController::class, 'store']);
    Route::get('/promotions/{promotion}', [AdminPromotionController::class, 'show']);
    Route::put('/promotions/{promotion}', [AdminPromotionController::class, 'update']);
    Route::patch('/promotions/{promotion}/status', [AdminPromotionController::class, 'updateStatus']);
    Route::get('/promotions/{promotion}/usage', [AdminPromotionController::class, 'usage']);

    Route::get('/growth/dashboard', [AdminGrowthController::class, 'dashboard']);
    Route::get('/growth/segments', [AdminGrowthController::class, 'segments']);
    Route::post('/growth/segments', [AdminGrowthController::class, 'storeSegment']);
    Route::put('/growth/segments/{segment}', [AdminGrowthController::class, 'updateSegment']);
    Route::post('/growth/segments/{segment}/refresh', [AdminGrowthController::class, 'refreshSegment']);
    Route::get('/growth/campaigns', [AdminGrowthController::class, 'campaigns']);
    Route::post('/growth/campaigns', [AdminGrowthController::class, 'storeCampaign']);
    Route::get('/growth/campaigns/{campaign}', [AdminGrowthController::class, 'showCampaign']);
    Route::put('/growth/campaigns/{campaign}', [AdminGrowthController::class, 'updateCampaign']);
    Route::post('/growth/campaigns/{campaign}/send', [AdminGrowthController::class, 'sendCampaign']);
    Route::get('/growth/campaigns/{campaign}/analytics', [AdminGrowthController::class, 'campaignAnalytics']);
    Route::get('/growth/journeys', [AdminGrowthController::class, 'journeys']);
    Route::post('/growth/journeys', [AdminGrowthController::class, 'storeJourney']);
    Route::post('/growth/journeys/run', [AdminGrowthController::class, 'runJourneys']);

    Route::get('/cms/homepage-layouts', [AdminCmsHomepageController::class, 'index']);
    Route::post('/cms/homepage-layouts', [AdminCmsHomepageController::class, 'store']);
    Route::get('/cms/homepage-layouts/{layout}', [AdminCmsHomepageController::class, 'show']);
    Route::put('/cms/homepage-layouts/{layout}', [AdminCmsHomepageController::class, 'update']);
    Route::post('/cms/homepage-layouts/{layout}/default', [AdminCmsHomepageController::class, 'setDefault']);
    Route::post('/cms/homepage-layouts/{layout}/archive', [AdminCmsHomepageController::class, 'archive']);
    Route::get('/cms/homepage-layouts/{layout}/sections', [AdminCmsHomepageController::class, 'sections']);
    Route::post('/cms/homepage-layouts/{layout}/sections', [AdminCmsHomepageController::class, 'storeSection']);
    Route::put('/cms/homepage-layouts/{layout}/sections/reorder', [AdminCmsHomepageController::class, 'reorderSections']);
    Route::put('/cms/homepage-layouts/{layout}/sections/{section}', [AdminCmsHomepageController::class, 'updateSection']);
    Route::post('/cms/homepage-layouts/{layout}/sections/{section}/visibility', [AdminCmsHomepageController::class, 'toggleSectionVisibility']);
    Route::delete('/cms/homepage-layouts/{layout}/sections/{section}', [AdminCmsHomepageController::class, 'destroySection']);

    Route::get('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides', [AdminCmsHeroSlideController::class, 'index']);
    Route::post('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides', [AdminCmsHeroSlideController::class, 'store']);
    Route::put('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides/reorder', [AdminCmsHeroSlideController::class, 'reorder']);
    Route::get('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides/{heroSlide}', [AdminCmsHeroSlideController::class, 'show']);
    Route::put('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides/{heroSlide}', [AdminCmsHeroSlideController::class, 'update']);
    Route::post('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides/{heroSlide}/activate', [AdminCmsHeroSlideController::class, 'activate']);
    Route::post('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides/{heroSlide}/archive', [AdminCmsHeroSlideController::class, 'archive']);
    Route::post('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides/{heroSlide}/visibility', [AdminCmsHeroSlideController::class, 'toggleVisibility']);
    Route::delete('/cms/homepage-layouts/{layout}/sections/{section}/hero-slides/{heroSlide}', [AdminCmsHeroSlideController::class, 'destroy']);

    Route::get('/cms/homepage-layouts/{layout}/sections/{section}/featured-contents', [AdminCmsFeaturedContentController::class, 'index']);
    Route::post('/cms/homepage-layouts/{layout}/sections/{section}/featured-contents', [AdminCmsFeaturedContentController::class, 'store']);
    Route::put('/cms/homepage-layouts/{layout}/sections/{section}/featured-contents/reorder', [AdminCmsFeaturedContentController::class, 'reorder']);
    Route::get('/cms/homepage-layouts/{layout}/sections/{section}/featured-contents/{featuredContent}', [AdminCmsFeaturedContentController::class, 'show']);
    Route::put('/cms/homepage-layouts/{layout}/sections/{section}/featured-contents/{featuredContent}', [AdminCmsFeaturedContentController::class, 'update']);
    Route::post('/cms/homepage-layouts/{layout}/sections/{section}/featured-contents/{featuredContent}/visibility', [AdminCmsFeaturedContentController::class, 'toggleVisibility']);
    Route::delete('/cms/homepage-layouts/{layout}/sections/{section}/featured-contents/{featuredContent}', [AdminCmsFeaturedContentController::class, 'destroy']);

    Route::get('/cms/campaigns', [AdminCmsCampaignController::class, 'index']);
    Route::post('/cms/campaigns', [AdminCmsCampaignController::class, 'store']);
    Route::get('/cms/campaigns/{campaign}', [AdminCmsCampaignController::class, 'show']);
    Route::put('/cms/campaigns/{campaign}', [AdminCmsCampaignController::class, 'update']);
    Route::post('/cms/campaigns/{campaign}/activate', [AdminCmsCampaignController::class, 'activate']);
    Route::post('/cms/campaigns/{campaign}/archive', [AdminCmsCampaignController::class, 'archive']);
    Route::patch('/cms/campaigns/{campaign}/priority', [AdminCmsCampaignController::class, 'updatePriority']);
    Route::post('/cms/campaigns/{campaign}/layout', [AdminCmsCampaignController::class, 'attachLayout']);
    Route::put('/cms/campaigns/{campaign}/hero-slides', [AdminCmsCampaignController::class, 'attachHeroSlides']);
    Route::put('/cms/campaigns/{campaign}/featured-contents', [AdminCmsCampaignController::class, 'attachFeaturedContents']);
    Route::put('/cms/campaigns/{campaign}/promotions', [AdminCmsCampaignController::class, 'attachPromotions']);
    Route::put('/cms/campaigns/{campaign}/navigation-shells', [AdminCmsCampaignController::class, 'attachNavigationShells']);

    Route::get('/cms/navigation-shells', [AdminCmsNavigationController::class, 'index']);
    Route::post('/cms/navigation-shells', [AdminCmsNavigationController::class, 'store']);
    Route::get('/cms/navigation-shells/{navigationShell}', [AdminCmsNavigationController::class, 'show']);
    Route::put('/cms/navigation-shells/{navigationShell}', [AdminCmsNavigationController::class, 'update']);
    Route::post('/cms/navigation-shells/{navigationShell}/publish', [AdminCmsNavigationController::class, 'publish']);
    Route::post('/cms/navigation-shells/{navigationShell}/archive', [AdminCmsNavigationController::class, 'archive']);
    Route::post('/cms/navigation-shells/{navigationShell}/default', [AdminCmsNavigationController::class, 'setDefault']);
    Route::delete('/cms/navigation-shells/{navigationShell}', [AdminCmsNavigationController::class, 'destroy']);
    Route::get('/cms/navigation-shells/{navigationShell}/items', [AdminCmsNavigationController::class, 'items']);
    Route::post('/cms/navigation-shells/{navigationShell}/items', [AdminCmsNavigationController::class, 'storeItem']);
    Route::put('/cms/navigation-shells/{navigationShell}/items/reorder', [AdminCmsNavigationController::class, 'reorderItems']);
    Route::put('/cms/navigation-shells/{navigationShell}/items/{item}', [AdminCmsNavigationController::class, 'updateItem']);
    Route::post('/cms/navigation-shells/{navigationShell}/items/{item}/enable', [AdminCmsNavigationController::class, 'enableItem']);
    Route::post('/cms/navigation-shells/{navigationShell}/items/{item}/disable', [AdminCmsNavigationController::class, 'disableItem']);
    Route::delete('/cms/navigation-shells/{navigationShell}/items/{item}', [AdminCmsNavigationController::class, 'destroyItem']);

    Route::get('/loyalty/dashboard', [AdminLoyaltyController::class, 'dashboard']);
    Route::get('/loyalty/customers', [AdminLoyaltyController::class, 'customers']);
    Route::get('/loyalty/customers/{account}', [AdminLoyaltyController::class, 'showCustomer']);
    Route::get('/loyalty/customers/{account}/ledger', [AdminLoyaltyController::class, 'ledger']);
    Route::post('/loyalty/customers/{account}/adjust', [AdminLoyaltyController::class, 'adjust']);
    Route::post('/loyalty/customers/{account}/redeem', [AdminLoyaltyController::class, 'redeem']);
    Route::post('/loyalty/enroll/{customer}', [AdminLoyaltyController::class, 'enroll']);
    Route::get('/loyalty/redemptions', [AdminLoyaltyController::class, 'redemptions']);
    Route::get('/loyalty/lookup', [AdminLoyaltyController::class, 'lookup']);
    Route::get('/loyalty/tiers', [AdminLoyaltyController::class, 'tiers']);
    Route::post('/loyalty/tiers', [AdminLoyaltyController::class, 'storeTier']);
    Route::put('/loyalty/tiers/{tier}', [AdminLoyaltyController::class, 'updateTier']);
    Route::get('/loyalty/rules', [AdminLoyaltyController::class, 'rules']);
    Route::post('/loyalty/rules', [AdminLoyaltyController::class, 'storeRule']);
    Route::put('/loyalty/rules/{rule}', [AdminLoyaltyController::class, 'updateRule']);
    Route::get('/loyalty/rewards', [AdminLoyaltyController::class, 'rewards']);
    Route::post('/loyalty/rewards', [AdminLoyaltyController::class, 'storeReward']);
    Route::put('/loyalty/rewards/{reward}', [AdminLoyaltyController::class, 'updateReward']);

    // RC1-G4B pattern: route middleware for CRM reads; FormRequest/controller authorize for writes.
    Route::middleware('admin.permission:customers.view')->group(function () {
        Route::get('/customers/summary', [AdminCustomerController::class, 'summary']);
        Route::get('/customers', [AdminCustomerController::class, 'index']);
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show']);
        Route::get('/customers/{customer}/orders', [AdminCustomerController::class, 'orders']);
        Route::get('/customers/{customer}/payments', [AdminCustomerController::class, 'payments']);
        Route::get('/customers/{customer}/shipments', [AdminCustomerController::class, 'shipments']);
        Route::get('/customers/{customer}/returns', [AdminCustomerController::class, 'returns']);
        Route::get('/customers/{customer}/addresses', [AdminCustomerController::class, 'addresses']);
        Route::get('/customers/{customer}/timeline', [AdminCustomerController::class, 'timeline']);
        Route::get('/customers/{customer}/notes', [AdminCustomerController::class, 'notes']);
        Route::get('/customer-tags', [AdminCustomerTagController::class, 'index']);
    });
    Route::patch('/customers/{customer}/status', [AdminCustomerController::class, 'updateStatus'])
        ->middleware('throttle:admin-mutations');
    Route::post('/customers/{customer}/metrics/rebuild', [AdminCustomerController::class, 'rebuildMetrics'])
        ->middleware('throttle:admin-mutations');
    Route::post('/customers/{customer}/tags', [AdminCustomerController::class, 'assignTag'])
        ->middleware('throttle:admin-mutations');
    Route::delete('/customers/{customer}/tags/{tag}', [AdminCustomerController::class, 'removeTag'])
        ->middleware('throttle:admin-mutations');
    Route::post('/customers/{customer}/notes', [AdminCustomerController::class, 'storeNote'])
        ->middleware('throttle:admin-mutations');
    Route::patch('/customers/{customer}/notes/{note}', [AdminCustomerController::class, 'updateNote'])
        ->middleware('throttle:admin-mutations');
    Route::delete('/customers/{customer}/notes/{note}', [AdminCustomerController::class, 'destroyNote'])
        ->middleware('throttle:admin-mutations');
    Route::post('/customer-tags', [AdminCustomerTagController::class, 'store'])
        ->middleware('throttle:admin-mutations');
    Route::patch('/customer-tags/{tag}', [AdminCustomerTagController::class, 'update'])
        ->middleware('throttle:admin-mutations');
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/trash', [AdminProductController::class, 'trash']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{product}', [AdminProductController::class, 'show']);
    Route::get('/products/{product}/images', [AdminProductController::class, 'indexImages']);
    Route::post('/products/{product}/images', [AdminProductController::class, 'storeImage'])
        ->middleware('throttle:uploads');
    Route::get('/products/{product}/media', [AdminProductMediaController::class, 'index']);
    Route::post('/products/{product}/media', [AdminProductMediaController::class, 'store'])
        ->middleware('throttle:uploads');
    Route::put('/products/{product}/media/{media}', [AdminProductMediaController::class, 'update'])
        ->middleware('throttle:uploads');
    Route::delete('/products/{product}/media/{media}', [AdminProductMediaController::class, 'destroy'])
        ->middleware('throttle:admin-mutations');
    Route::post('/products/{product}/media/{media}/primary', [AdminProductMediaController::class, 'setPrimary'])
        ->middleware('throttle:admin-mutations');
    Route::get('/products/{product}/attributes', [AdminProductAttributeController::class, 'index']);
    Route::put('/products/{product}/attributes', [AdminProductAttributeController::class, 'sync']);
    Route::get('/products/{product}/variants', [AdminProductVariantController::class, 'index']);
    Route::post('/products/{product}/variants', [AdminProductVariantController::class, 'store']);
    Route::post('/products/{product}/variants/generate', [AdminProductVariantController::class, 'generate']);
    Route::put('/products/{product}/variants/{variant}', [AdminProductVariantController::class, 'update']);
    Route::delete('/products/{product}/variants/{variant}', [AdminProductVariantController::class, 'destroy']);
    Route::get('/products/{product}/shipping-options', [AdminProductShippingOptionController::class, 'index']);
    Route::post('/products/{product}/shipping-options', [AdminProductShippingOptionController::class, 'store']);
    Route::put('/products/{product}/shipping-options/sync', [AdminProductShippingOptionController::class, 'sync']);
    Route::get('/products/{product}/shipping-options/{shippingOption}', [AdminProductShippingOptionController::class, 'show']);
    Route::put('/products/{product}/shipping-options/{shippingOption}', [AdminProductShippingOptionController::class, 'update']);
    Route::delete('/products/{product}/shipping-options/{shippingOption}', [AdminProductShippingOptionController::class, 'destroy']);
    Route::post('/products/{product}/shipping-options/{id}/restore', [AdminProductShippingOptionController::class, 'restore']);
    Route::get('/variants/{variant}/prices', [AdminVariantPriceController::class, 'index']);
    Route::post('/variants/{variant}/prices', [AdminVariantPriceController::class, 'store']);
    Route::put('/prices/{price}', [AdminVariantPriceController::class, 'update']);
    Route::delete('/prices/{price}', [AdminVariantPriceController::class, 'destroy']);
    Route::get('/variants/{variant}/inventory', [AdminVariantInventoryController::class, 'index']);
    Route::post('/variants/{variant}/inventory', [AdminVariantInventoryController::class, 'store']);
    Route::put('/inventory/{inventory}', [AdminVariantInventoryController::class, 'update']);
    Route::delete('/inventory/{inventory}', [AdminVariantInventoryController::class, 'destroy']);

    Route::get('/inventory', [AdminInventoryController::class, 'dashboard']);
    Route::get('/inventory/stock', [AdminInventoryController::class, 'stockLevels']);
    Route::get('/inventory/movements', [AdminInventoryController::class, 'movements']);
    Route::get('/inventory/receiving', [AdminInventoryController::class, 'receiving']);
    Route::post('/inventory/adjustments', [AdminInventoryController::class, 'adjust']);
    Route::get('/inventory/counts', [AdminInventoryController::class, 'counts']);
    Route::post('/inventory/counts', [AdminInventoryController::class, 'createCount']);
    Route::get('/inventory/counts/{count}', [AdminInventoryController::class, 'showCount']);
    Route::post('/inventory/counts/{count}/lines', [AdminInventoryController::class, 'recordCount']);
    Route::post('/inventory/counts/{count}/submit', [AdminInventoryController::class, 'submitCount']);
    Route::post('/inventory/counts/{count}/approve', [AdminInventoryController::class, 'approveCount']);
    Route::get('/inventory/valuation', [AdminInventoryController::class, 'valuation']);
    Route::get('/inventory/low-stock', [AdminInventoryController::class, 'lowStock']);
    Route::patch('/products/{product}/stock', [AdminProductController::class, 'updateStock']);
    Route::get('/products/{product}/inventory/movements', [AdminProductController::class, 'indexInventoryMovements']);
    Route::post('/products/{product}/quote', [AdminProductController::class, 'quote']);
    Route::put('/products/{product}/price-tiers', [AdminProductController::class, 'syncPriceTiers']);
    Route::put('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
    Route::post('/products/{id}/restore', [AdminProductController::class, 'restore']);
    Route::delete('/products/{id}/force', [AdminProductController::class, 'forceDestroy']);
    Route::delete('/product-images/{image}', [AdminProductImageController::class, 'destroy']);
    Route::patch('/product-images/{image}/primary', [AdminProductImageController::class, 'setPrimary']);
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::get('/categories/{category}/product-form-schema', [AdminProductTypeController::class, 'formSchema']);
    Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::post('/categories/{id}/restore', [AdminCategoryController::class, 'restore']);
    Route::get('/product-types', [AdminProductTypeController::class, 'index']);
    Route::get('/product-types/{productType}', [AdminProductTypeController::class, 'show']);
    Route::post('/product-types/{productType}/generate-configurations', [AdminProductTypeController::class, 'generateConfigurations']);
    Route::get('/brands', [AdminBrandController::class, 'index']);
    Route::post('/brands', [AdminBrandController::class, 'store']);
    Route::get('/brands/{brand}', [AdminBrandController::class, 'show']);
    Route::put('/brands/{brand}', [AdminBrandController::class, 'update']);
    Route::put('/brands/{brand}/categories', [AdminBrandController::class, 'syncCategories']);
    Route::post('/brands/{brand}/assets', [AdminBrandController::class, 'uploadAsset'])
        ->middleware('throttle:uploads');
    Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy']);
    Route::post('/brands/{id}/restore', [AdminBrandController::class, 'restore']);
    Route::get('/departments', [AdminDepartmentController::class, 'index']);
    Route::post('/departments', [AdminDepartmentController::class, 'store']);
    Route::get('/departments/{department}', [AdminDepartmentController::class, 'show']);
    Route::put('/departments/{department}', [AdminDepartmentController::class, 'update']);
    Route::delete('/departments/{department}', [AdminDepartmentController::class, 'destroy']);
    Route::post('/departments/{id}/restore', [AdminDepartmentController::class, 'restore']);
    Route::get('/subcategories', [AdminSubcategoryController::class, 'index']);
    Route::post('/subcategories', [AdminSubcategoryController::class, 'store']);
    Route::get('/subcategories/{subcategory}', [AdminSubcategoryController::class, 'show']);
    Route::put('/subcategories/{subcategory}', [AdminSubcategoryController::class, 'update']);
    Route::delete('/subcategories/{subcategory}', [AdminSubcategoryController::class, 'destroy']);
    Route::post('/subcategories/{id}/restore', [AdminSubcategoryController::class, 'restore']);
    Route::get('/catalog-product-types', [AdminCatalogProductTypeController::class, 'index']);
    Route::post('/catalog-product-types', [AdminCatalogProductTypeController::class, 'store']);
    Route::get('/catalog-product-types/{catalogProductType}', [AdminCatalogProductTypeController::class, 'show']);
    Route::put('/catalog-product-types/{catalogProductType}', [AdminCatalogProductTypeController::class, 'update']);
    Route::delete('/catalog-product-types/{catalogProductType}', [AdminCatalogProductTypeController::class, 'destroy']);
    Route::post('/catalog-product-types/{id}/restore', [AdminCatalogProductTypeController::class, 'restore']);
    Route::put('/catalog-product-types/{catalogProductType}/attributes', [AdminCatalogAttributeController::class, 'syncProductTypeAttributes']);
    Route::get('/catalog-attributes', [AdminCatalogAttributeController::class, 'index']);
    Route::post('/catalog-attributes', [AdminCatalogAttributeController::class, 'store']);
    Route::get('/catalog-attributes/filters', [AdminCatalogAttributeController::class, 'filters']);
    Route::get('/catalog-attributes/{catalogAttribute}', [AdminCatalogAttributeController::class, 'show']);
    Route::put('/catalog-attributes/{catalogAttribute}', [AdminCatalogAttributeController::class, 'update']);
    Route::delete('/catalog-attributes/{catalogAttribute}', [AdminCatalogAttributeController::class, 'destroy']);
    Route::post('/catalog-attributes/{id}/restore', [AdminCatalogAttributeController::class, 'restore']);
    Route::post('/catalog-attributes/{catalogAttribute}/options', [AdminCatalogAttributeController::class, 'storeOption']);
    Route::put('/catalog-attribute-options/{catalogAttributeOption}', [AdminCatalogAttributeController::class, 'updateOption']);
    Route::delete('/catalog-attribute-options/{catalogAttributeOption}', [AdminCatalogAttributeController::class, 'destroyOption']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::post('/orders', [AdminOrderController::class, 'store']);
    Route::patch('/orders/{order}/pay', [AdminOrderController::class, 'pay']);
    Route::patch('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);
    Route::post('/orders/{order}/refunds/complete', [AdminOrderController::class, 'completeCancellationRefund']);
    Route::post('/orders/{order}/refunds/fail', [AdminOrderController::class, 'failCancellationRefund']);
    Route::patch('/orders/{order}/shipment-status', [AdminShipmentController::class, 'update']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::get('/fulfillments', [AdminFulfillmentController::class, 'index']);
    Route::post('/fulfillments/create/{order}', [AdminFulfillmentController::class, 'create']);
    Route::get('/fulfillments/{fulfillment}', [AdminFulfillmentController::class, 'show']);
    Route::patch('/fulfillments/{fulfillment}/status', [AdminFulfillmentController::class, 'updateStatus']);
    Route::get('/fulfillments/{fulfillment}/shipment-eligibility', [AdminShipmentsController::class, 'eligibility']);
    Route::get('/warehouse', [AdminWarehouseController::class, 'index']);
    Route::get('/warehouse/{job}', [AdminWarehouseController::class, 'show']);
    Route::patch('/warehouse/{job}/status', [AdminWarehouseController::class, 'updateStatus']);
    Route::patch('/warehouse/{job}/assign-picker', [AdminWarehouseController::class, 'assignPicker']);
    Route::patch('/warehouse/{job}/assign-packer', [AdminWarehouseController::class, 'assignPacker']);
    Route::get('/notifications', [AdminNotificationController::class, 'index']);
    Route::get('/notification-templates', [AdminNotificationTemplateController::class, 'index']);
    Route::post('/notification-templates', [AdminNotificationTemplateController::class, 'store']);
    Route::get('/notification-templates/{template}', [AdminNotificationTemplateController::class, 'show']);
    Route::put('/notification-templates/{template}', [AdminNotificationTemplateController::class, 'update']);
    Route::post('/notification-templates/{template}/preview', [AdminNotificationTemplateController::class, 'preview']);
    Route::get('/activity-logs', [AdminActivityLogController::class, 'index']);
    Route::get('/activity-logs/{activityLog}', [AdminActivityLogController::class, 'show']);
    Route::get('/returns', [AdminReturnController::class, 'index']);
    Route::get('/returns/{returnRequest}', [AdminReturnController::class, 'show']);
    Route::patch('/returns/{returnRequest}/status', [AdminReturnController::class, 'updateStatus']);
    Route::post('/returns/{returnRequest}/refund', [AdminReturnController::class, 'createRefund']);
    Route::get('/shipments', [AdminShipmentsController::class, 'index']);
    Route::post('/shipments/create/{fulfillment}', [AdminShipmentsController::class, 'create']);
    Route::get('/shipments/{shipment}', [AdminShipmentsController::class, 'show']);
    Route::patch('/shipments/{shipment}/status', [AdminShipmentsController::class, 'updateStatus']);
    Route::get('/shipments/{shipment}/tracking', [AdminShipmentTrackingController::class, 'index']);
    Route::post('/shipments/{shipment}/tracking', [AdminShipmentTrackingController::class, 'store']);
    Route::post('/orders/{order}/delivery-option/confirm-negotiated', [AdminDeliveryOptionController::class, 'confirmNegotiated'])
        ->middleware('throttle:admin-mutations');
    Route::get('/payments', [AdminPaymentController::class, 'index']);
    Route::post('/payments', [AdminPaymentController::class, 'store']);
    Route::post('/payments/{payment}/mock', [AdminMockPaymentController::class, 'process']);
    Route::post('/payments/{payment}/simulate-nmb-callback', [AdminSimulateNmbCallbackController::class, 'store']);
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show']);
    Route::put('/payments/{payment}', [AdminPaymentController::class, 'update']);
    Route::delete('/payments/{payment}', [AdminPaymentController::class, 'destroy']);
    Route::get('/me', [AdminAuthController::class, 'me'])
        ->middleware('throttle:admin-profile');
    Route::post('/logout', [AdminAuthController::class, 'logout']);
});
