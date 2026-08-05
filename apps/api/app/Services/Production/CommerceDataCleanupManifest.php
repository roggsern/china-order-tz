<?php

namespace App\Services\Production;

/**
 * Explicit tables for production commerce cleanup.
 *
 * DELETE = transactional/test commerce data.
 * PRESERVE = foundation/configuration (never touched by this command).
 */
final class CommerceDataCleanupManifest
{
    public const CONFIRMATION_PHRASE = 'DELETE_TEST_COMMERCE_DATA';

    /**
     * Child → parent deletion order. Schema::hasTable gates missing tables.
     *
     * @var list<string>
     */
    public const DELETION_ORDER = [
        // Support
        'support_messages',
        'support_tickets',

        // Storefront / AI analytics (runtime)
        'storefront_events',
        'storefront_sessions',
        'storefront_visitors',
        'ai_search_logs',
        'ai_recommendations',
        'ai_image_search_sessions',

        // Growth runtime (preserve campaign/journey/segment definitions)
        'growth_campaign_deliveries',
        'growth_journey_enrollments',
        'growth_segment_members',

        // Activity / audit runtime
        'activity_logs',
        'audit_logs',

        // Notifications (instances + customer prefs; templates preserved)
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

        // Warehouse ops bound to orders/variants
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

        // Procurement / receiving (transactional)
        'receiving_record_items',
        'receiving_records',
        'purchase_order_items',
        'purchase_orders',
        'supplier_cost_histories',

        // Inventory transactional
        'inventory_count_lines',
        'inventory_count_sessions',
        'inventory_stock_movements',
        'inventory_logs',
        'inventory_movements',
        'variant_inventories',
        'inventory',
        'product_variant_warehouse_bins',
        'china_commercial_stocks',

        // Product instances + media rows
        'product_channel_backfill_logs',
        'product_store_backfill_logs',
        'product_shipping_backfill_logs',
        'product_embeddings',
        'configuration_price_tiers',
        'variant_prices',
        'product_variant_attribute_values',
        'product_variant_attribute_value',
        'catalog_product_attribute_values',
        'product_media',
        'product_images',
        'product_shipping_options',
        'category_product',
        'supplier_products',
        'product_variants',
        'products',

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
     * Domain → tables for dry-run / report grouping.
     *
     * @var array<string, list<string>>
     */
    public const DOMAIN_TABLES = [
        'products' => ['products'],
        'variants' => ['product_variants'],
        'product_media' => ['product_media', 'product_images'],
        'product_commerce' => [
            'variant_prices',
            'configuration_price_tiers',
            'catalog_product_attribute_values',
            'product_variant_attribute_values',
            'product_variant_attribute_value',
            'product_shipping_options',
            'category_product',
            'supplier_products',
            'product_embeddings',
            'product_channel_backfill_logs',
            'product_store_backfill_logs',
            'product_shipping_backfill_logs',
            'china_commercial_stocks',
            'product_variant_warehouse_bins',
        ],
        'inventory' => [
            'inventory',
            'variant_inventories',
            'inventory_stock_movements',
            'inventory_logs',
            'inventory_movements',
            'inventory_count_lines',
            'inventory_count_sessions',
        ],
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
     * Preserve checks reported on dry-run / post-cleanup.
     *
     * @var array<string, string|list<string>>
     */
    public const PRESERVE_CHECKS = [
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
