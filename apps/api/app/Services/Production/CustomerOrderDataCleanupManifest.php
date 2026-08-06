<?php

namespace App\Services\Production;

/**
 * Pre-launch customer + order cleanup.
 *
 * Deletes transactional customer/order graph only.
 * NEVER includes products, variants, inventory stock rows, or product media.
 */
final class CustomerOrderDataCleanupManifest
{
    public const CONFIRMATION_PHRASE = 'DELETE_PRELAUNCH_CUSTOMERS_AND_ORDERS';

    /**
     * Tables that must never appear in DELETION_ORDER (catalog integrity).
     *
     * @var list<string>
     */
    public const FORBIDDEN_DELETE_TABLES = [
        'products',
        'product_variants',
        'variant_prices',
        'configuration_price_tiers',
        'product_media',
        'product_images',
        'product_embeddings',
        'product_shipping_options',
        'catalog_product_attribute_values',
        'product_variant_attribute_values',
        'product_variant_attribute_value',
        'category_product',
        'supplier_products',
        'inventory',
        'variant_inventories',
        'china_commercial_stocks',
        'product_variant_warehouse_bins',
        'product_channel_backfill_logs',
        'product_store_backfill_logs',
        'product_shipping_backfill_logs',
        'admins',
        'roles',
        'permissions',
        'stores',
        'departments',
        'categories',
        'catalog_product_types',
        'catalog_attributes',
        'catalog_attribute_options',
        'suppliers',
        'commerce_channels',
        'notification_templates',
        'settings',
        'cms_homepage_layouts',
        'cms_navigation_shells',
        'cms_navigation_items',
        'shipping_methods',
        'shipping_rates',
        'payment_methods',
        'warehouse_facilities',
        'coupons',
        'promotions',
        'loyalty_tiers',
        'customer_tags',
        'media',
    ];

    /**
     * Child → parent deletion order. Schema::hasTable gates missing tables.
     *
     * @var list<string>
     */
    public const DELETION_ORDER = [
        // Support runtime
        'support_messages',
        'support_tickets',

        // Storefront / AI analytics runtime
        'storefront_events',
        'storefront_sessions',
        'storefront_visitors',
        'ai_search_logs',
        'ai_recommendations',
        'ai_image_search_sessions',

        // Growth runtime (definitions preserved)
        'growth_campaign_deliveries',
        'growth_journey_enrollments',
        'growth_segment_members',

        // Activity / audit runtime (customer/order evidence wiped with pre-launch reset)
        'activity_logs',
        'audit_logs',

        // Notifications (instances + prefs; templates preserved)
        'notifications',
        'notification_preferences',
        'email_change_requests',

        // Returns / refunds / loyalty ledger
        'refund_transactions',
        'return_items',
        'return_requests',
        'refunds',
        'loyalty_redemptions',
        'loyalty_ledger_entries',
        'loyalty_accounts',
        'coupon_usages',
        'promotion_usages',
        'order_discount_snapshots',

        // Warehouse / China ops bound to orders (not stock balances)
        'warehouse_packing_lines',
        'warehouse_pick_list_lines',
        'warehouse_packing_records',
        'warehouse_pick_lists',
        'warehouse_stock_transfer_lines',
        'warehouse_stock_transfers',
        'customer_agent_pickup_histories',
        'customer_agent_pickups',
        'china_workflow_histories',
        'china_workflow_records',
        'china_inventory_transfer_lines',
        'china_inventory_transfers',
        'china_procurement_requirement_links',
        'china_procurement_requirements',
        'fulfillment_status_histories',
        'pos_sale_idempotency_keys',
        'pos_receipts',

        // Payments / shipments / orders
        'shipment_tracking_events',
        'shipment_status_histories',
        'order_tracking_events',
        'order_status_history',
        'order_cost_snapshots',
        'profit_records',
        'payment_transactions',
        'payments',
        'delivery_options',
        'warehouse_jobs',
        'shipments',
        'fulfillments',
        'order_items',
        'shipping_addresses',
        'orders',
        'pos_sessions',

        // China customer orders
        'china_order_status_history',
        'china_order_quote_items',
        'china_order_quotes',
        'china_order_attachments',
        'china_order_source_links',
        'china_order_items',
        'china_order_requests',

        // Cart / checkout / reviews / wishlists
        'checkout_sessions',
        'cart_items',
        'carts',
        'review_images',
        'reviews',
        'wishlist_items',
        'wishlists',

        // Procurement / receiving runtime (not suppliers master)
        'receiving_record_items',
        'receiving_records',
        'purchase_order_items',
        'purchase_orders',
        'supplier_cost_histories',

        // Inventory transaction logs only — stock rows are preserved
        'inventory_count_lines',
        'inventory_count_sessions',
        'inventory_stock_movements',
        'inventory_logs',
        'inventory_movements',

        // Customer CRM / identity (after all user FKs)
        'customer_timeline_events',
        'customer_notes',
        'customer_profile_tag',
        'customer_metrics',
        'customer_profiles',
        'user_addresses',
        'user_profiles',
        'delivery_addresses',
        'role_user',
        'users',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const DOMAIN_TABLES = [
        'customers' => [
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
            'email_change_requests',
        ],
        'carts' => ['carts', 'cart_items', 'checkout_sessions'],
        'wishlists' => ['wishlists', 'wishlist_items'],
        'orders' => [
            'orders',
            'order_items',
            'shipping_addresses',
            'order_status_history',
            'order_tracking_events',
            'order_cost_snapshots',
            'order_discount_snapshots',
            'profit_records',
        ],
        'payments' => ['payments', 'payment_transactions'],
        'refunds' => ['refunds', 'refund_transactions', 'return_requests', 'return_items'],
        'fulfillments' => [
            'fulfillments',
            'fulfillment_status_histories',
            'warehouse_jobs',
            'delivery_options',
            'customer_agent_pickups',
            'customer_agent_pickup_histories',
            'china_workflow_records',
            'china_workflow_histories',
        ],
        'shipments' => [
            'shipments',
            'shipment_tracking_events',
            'shipment_status_histories',
        ],
        'warehouse_ops' => [
            'warehouse_pick_lists',
            'warehouse_pick_list_lines',
            'warehouse_packing_records',
            'warehouse_packing_lines',
            'warehouse_stock_transfers',
            'warehouse_stock_transfer_lines',
        ],
        'china_orders' => [
            'china_order_requests',
            'china_order_items',
            'china_order_quotes',
            'china_order_quote_items',
            'china_order_attachments',
            'china_order_source_links',
            'china_order_status_history',
        ],
        'china_procurement' => [
            'china_procurement_requirements',
            'china_procurement_requirement_links',
            'china_inventory_transfers',
            'china_inventory_transfer_lines',
        ],
        'procurement' => [
            'purchase_orders',
            'purchase_order_items',
            'receiving_records',
            'receiving_record_items',
            'supplier_cost_histories',
        ],
        'inventory_logs' => [
            'inventory_stock_movements',
            'inventory_logs',
            'inventory_movements',
            'inventory_count_lines',
            'inventory_count_sessions',
        ],
        'pos' => ['pos_sessions', 'pos_receipts', 'pos_sale_idempotency_keys'],
        'reviews' => ['reviews', 'review_images'],
        'support_tickets' => ['support_tickets', 'support_messages'],
        'notifications' => ['notifications', 'notification_preferences'],
        'analytics_events' => [
            'storefront_events',
            'storefront_sessions',
            'storefront_visitors',
            'ai_search_logs',
            'ai_recommendations',
            'ai_image_search_sessions',
            'activity_logs',
            'audit_logs',
        ],
        'loyalty_runtime' => [
            'loyalty_accounts',
            'loyalty_ledger_entries',
            'loyalty_redemptions',
        ],
        'promo_runtime' => ['coupon_usages', 'promotion_usages'],
        'growth_runtime' => [
            'growth_campaign_deliveries',
            'growth_journey_enrollments',
            'growth_segment_members',
        ],
    ];

    /**
     * Catalog + foundation that must remain (reported on dry-run / post-cleanup).
     *
     * @var array<string, string>
     */
    public const PRESERVE_CHECKS = [
        'products' => 'products',
        'product_variants' => 'product_variants',
        'variant_prices' => 'variant_prices',
        'product_media' => 'product_media',
        'product_images' => 'product_images',
        'inventory' => 'inventory',
        'variant_inventories' => 'variant_inventories',
        'china_commercial_stocks' => 'china_commercial_stocks',
        'admins' => 'admins',
        'stores' => 'stores',
        'departments' => 'departments',
        'categories' => 'categories',
        'catalog_product_types' => 'catalog_product_types',
        'catalog_attributes' => 'catalog_attributes',
        'catalog_attribute_options' => 'catalog_attribute_options',
        'notification_templates' => 'notification_templates',
        'settings' => 'settings',
        'cms_homepage_layouts' => 'cms_homepage_layouts',
        'cms_navigation_shells' => 'cms_navigation_shells',
        'cms_navigation_items' => 'cms_navigation_items',
        'roles' => 'roles',
        'permissions' => 'permissions',
        'commerce_channels' => 'commerce_channels',
        'suppliers' => 'suppliers',
        'shipping_methods' => 'shipping_methods',
        'shipping_rates' => 'shipping_rates',
        'payment_methods' => 'payment_methods',
        'warehouse_facilities' => 'warehouse_facilities',
        'coupons' => 'coupons',
        'promotions' => 'promotions',
        'loyalty_tiers' => 'loyalty_tiers',
        'customer_tags' => 'customer_tags',
        'media_cms' => 'media',
    ];
}
