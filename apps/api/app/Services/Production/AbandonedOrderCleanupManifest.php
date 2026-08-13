<?php

namespace App\Services\Production;

use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;

/**
 * Selective abandoned-order cleanup (orders + order-bound runtime only).
 *
 * Reuses table knowledge from CustomerOrderDataCleanupManifest but NEVER deletes
 * customers, catalog, settings, or foundation data.
 *
 * Schema-aware relation classes used by AbandonedOrderCleanupService:
 * - DIRECT_ORDER_ID (`order_id` strategy)
 * - VIA_ORDER_ITEM_ID (`order_item_id` strategy) — e.g. order_cost_snapshots
 * - VIA_OTHER_PARENT (`via_parent` strategy)
 * - MORPH/PAYLOAD (`morph`, `morph_reference`, `json_order_id`)
 * - GLOBAL / NOT SAFE — listed in FORBIDDEN_DELETE_TABLES
 *
 * Production keep-order for the Aug 2026 selective cleanup:
 * COTZ-20260811-000005 (pass via --keep-order; not a secret).
 */
final class AbandonedOrderCleanupManifest
{
    public const CONFIRMATION_PHRASE = 'DELETE_ABANDONED_ORDERS_KEEP_PAID';

    /**
     * Documented production protected order number (one-time cleanup identifier).
     * Always pass explicitly via --keep-order; this constant is documentation / tests only.
     */
    public const DOCUMENTED_PROTECTED_ORDER_NUMBER = 'COTZ-20260811-000005';

    /**
     * Order statuses that must never be deleted (financial / fulfilled states).
     *
     * @var list<string>
     */
    public const PROTECTED_ORDER_STATUSES = [
        OrderStatus::Paid->value,
        OrderStatus::Confirmed->value,
        OrderStatus::Processing->value,
        OrderStatus::Shipped->value,
        OrderStatus::Delivered->value,
        OrderStatus::Completed->value,
        OrderStatus::RefundPending->value,
        OrderStatus::Refunded->value,
    ];

    /**
     * Payment transaction statuses that abort the entire run if present on a candidate.
     *
     * @var list<string>
     */
    public const PROTECTED_PAYMENT_TRANSACTION_STATUSES = [
        PaymentTransactionStatus::Successful->value,
    ];

    /**
     * Tables that must never be truncated / bulk-deleted by this command.
     *
     * @var list<string>
     */
    public const FORBIDDEN_DELETE_TABLES = [
        'users',
        'customer_profiles',
        'customer_metrics',
        'customer_notes',
        'customer_timeline_events',
        'customer_profile_tag',
        'user_addresses',
        'user_profiles',
        'delivery_addresses',
        'role_user',
        'personal_access_tokens',
        'sessions',
        'password_reset_tokens',
        'carts',
        'cart_items',
        'checkout_sessions',
        'wishlists',
        'wishlist_items',
        'products',
        'product_variants',
        'variant_prices',
        'product_media',
        'inventory',
        'variant_inventories',
        'china_commercial_stocks',
        'admins',
        'roles',
        'permissions',
        'stores',
        'categories',
        'settings',
        'payment_methods',
        'shipping_methods',
        'coupons',
        'promotions',
        'notification_templates',
        'media',
    ];

    /**
     * Catalog / identity preserve checks reported on dry-run and post-verify.
     *
     * @var array<string, string>
     */
    public const PRESERVE_CHECKS = [
        'users' => 'users',
        'customer_profiles' => 'customer_profiles',
        'products' => 'products',
        'product_variants' => 'product_variants',
        'inventory' => 'inventory',
        'variant_inventories' => 'variant_inventories',
        'china_commercial_stocks' => 'china_commercial_stocks',
        'admins' => 'admins',
        'payment_methods' => 'payment_methods',
        'coupons' => 'coupons',
        'promotions' => 'promotions',
    ];
}
