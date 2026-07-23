<?php

namespace App\Support\Admin;

/**
 * Canonical admin permission taxonomy (machine-readable, domain-oriented).
 */
final class AdminPermissions
{
    // Admin management
    public const ADMINS_VIEW = 'admins.view';

    public const ADMINS_CREATE = 'admins.create';

    public const ADMINS_UPDATE = 'admins.update';

    public const ADMINS_ACTIVATE = 'admins.activate';

    public const ADMINS_DEACTIVATE = 'admins.deactivate';

    public const ADMINS_ASSIGN_ROLES = 'admins.assign_roles';

    // Catalog
    public const CATALOG_VIEW = 'catalog.view';

    public const CATALOG_CREATE = 'catalog.create';

    public const CATALOG_UPDATE = 'catalog.update';

    public const CATALOG_PUBLISH = 'catalog.publish';

    public const CATALOG_ARCHIVE = 'catalog.archive';

    public const CATALOG_RESTORE = 'catalog.restore';

    public const CATALOG_DELETE = 'catalog.delete';

    // Pricing
    public const PRICING_VIEW = 'pricing.view';

    public const PRICING_MANAGE = 'pricing.manage';

    // Inventory
    public const INVENTORY_VIEW = 'inventory.view';

    public const INVENTORY_RECEIVE = 'inventory.receive';

    public const INVENTORY_ADJUST = 'inventory.adjust';

    public const INVENTORY_TRANSFER = 'inventory.transfer';

    public const INVENTORY_RESERVE = 'inventory.reserve';

    public const INVENTORY_COMMIT = 'inventory.commit';

    public const INVENTORY_RELEASE = 'inventory.release';

    public const INVENTORY_RESTOCK = 'inventory.restock';

    // Orders
    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_UPDATE = 'orders.update';

    public const ORDERS_MARK_PAID = 'orders.mark_paid';

    public const ORDERS_CANCEL = 'orders.cancel';

    public const ORDERS_FULFILL = 'orders.fulfill';

    public const ORDERS_SHIP = 'orders.ship';

    public const ORDERS_COMPLETE = 'orders.complete';

    // Payments
    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYMENTS_RECONCILE = 'payments.reconcile';

    public const PAYMENTS_RETRY = 'payments.retry';

    public const PAYMENTS_REFUND = 'payments.refund';

    public const PAYMENTS_MANAGE_MANUAL = 'payments.manage_manual';

    // Returns
    public const RETURNS_VIEW = 'returns.view';

    public const RETURNS_MANAGE = 'returns.manage';

    public const RETURNS_APPROVE = 'returns.approve';

    public const RETURNS_REJECT = 'returns.reject';

    public const RETURNS_REFUND = 'returns.refund';

    // Customers
    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_UPDATE = 'customers.update';

    public const CUSTOMERS_BLOCK = 'customers.block';

    public const CUSTOMERS_MANAGE_TAGS = 'customers.manage_tags';

    public const CUSTOMERS_MANAGE_NOTES = 'customers.manage_notes';

    // Configuration
    public const CONFIGURATION_VIEW = 'configuration.view';

    public const CONFIGURATION_MANAGE = 'configuration.manage';

    // CMS
    public const CMS_VIEW = 'cms.view';

    public const CMS_MANAGE = 'cms.manage';

    public const CMS_PUBLISH = 'cms.publish';

    // Settings
    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    // Reports
    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_EXPORT = 'reports.export';

    // Analytics / profit / growth (RC1-G2)
    public const ANALYTICS_VIEW = 'analytics.view';

    public const ANALYTICS_EXPORT = 'analytics.export';

    public const PROFIT_REPORTS_VIEW = 'profit_reports.view';

    public const GROWTH_VIEW = 'growth.view';

    public const GROWTH_MANAGE = 'growth.manage';

    // Suppliers / procurement / purchase orders
    public const SUPPLIERS_VIEW = 'suppliers.view';

    public const SUPPLIERS_CREATE = 'suppliers.create';

    public const SUPPLIERS_UPDATE = 'suppliers.update';

    public const SUPPLIERS_DELETE = 'suppliers.delete';

    public const PROCUREMENT_VIEW = 'procurement.view';

    public const PROCUREMENT_CREATE = 'procurement.create';

    public const PROCUREMENT_UPDATE = 'procurement.update';

    public const PROCUREMENT_DELETE = 'procurement.delete';

    public const PURCHASE_ORDERS_VIEW = 'purchase_orders.view';

    public const PURCHASE_ORDERS_CREATE = 'purchase_orders.create';

    public const PURCHASE_ORDERS_UPDATE = 'purchase_orders.update';

    public const PURCHASE_ORDERS_APPROVE = 'purchase_orders.approve';

    public const PURCHASE_ORDERS_RECEIVE = 'purchase_orders.receive';

    public const PURCHASE_ORDERS_CANCEL = 'purchase_orders.cancel';

    // Warehouse jobs
    public const WAREHOUSE_VIEW = 'warehouse.view';

    public const WAREHOUSE_JOBS_VIEW = 'warehouse.jobs.view';

    public const WAREHOUSE_JOBS_CREATE = 'warehouse.jobs.create';

    public const WAREHOUSE_JOBS_UPDATE = 'warehouse.jobs.update';

    public const WAREHOUSE_JOBS_COMPLETE = 'warehouse.jobs.complete';

    public const WAREHOUSE_JOBS_DELETE = 'warehouse.jobs.delete';

    // Promotions
    public const PROMOTIONS_VIEW = 'promotions.view';

    public const PROMOTIONS_CREATE = 'promotions.create';

    public const PROMOTIONS_UPDATE = 'promotions.update';

    public const PROMOTIONS_DELETE = 'promotions.delete';

    // Loyalty
    public const LOYALTY_VIEW = 'loyalty.view';

    public const LOYALTY_MANAGE = 'loyalty.manage';

    // Notification templates / activity
    public const NOTIFICATIONS_TEMPLATES_VIEW = 'notifications.templates.view';

    public const NOTIFICATIONS_TEMPLATES_MANAGE = 'notifications.templates.manage';

    public const ACTIVITY_LOGS_VIEW = 'activity_logs.view';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ADMINS_VIEW,
            self::ADMINS_CREATE,
            self::ADMINS_UPDATE,
            self::ADMINS_ACTIVATE,
            self::ADMINS_DEACTIVATE,
            self::ADMINS_ASSIGN_ROLES,
            self::CATALOG_VIEW,
            self::CATALOG_CREATE,
            self::CATALOG_UPDATE,
            self::CATALOG_PUBLISH,
            self::CATALOG_ARCHIVE,
            self::CATALOG_RESTORE,
            self::CATALOG_DELETE,
            self::PRICING_VIEW,
            self::PRICING_MANAGE,
            self::INVENTORY_VIEW,
            self::INVENTORY_RECEIVE,
            self::INVENTORY_ADJUST,
            self::INVENTORY_TRANSFER,
            self::INVENTORY_RESERVE,
            self::INVENTORY_COMMIT,
            self::INVENTORY_RELEASE,
            self::INVENTORY_RESTOCK,
            self::ORDERS_VIEW,
            self::ORDERS_UPDATE,
            self::ORDERS_MARK_PAID,
            self::ORDERS_CANCEL,
            self::ORDERS_FULFILL,
            self::ORDERS_SHIP,
            self::ORDERS_COMPLETE,
            self::PAYMENTS_VIEW,
            self::PAYMENTS_RECONCILE,
            self::PAYMENTS_RETRY,
            self::PAYMENTS_REFUND,
            self::PAYMENTS_MANAGE_MANUAL,
            self::RETURNS_VIEW,
            self::RETURNS_MANAGE,
            self::RETURNS_APPROVE,
            self::RETURNS_REJECT,
            self::RETURNS_REFUND,
            self::CUSTOMERS_VIEW,
            self::CUSTOMERS_UPDATE,
            self::CUSTOMERS_BLOCK,
            self::CUSTOMERS_MANAGE_TAGS,
            self::CUSTOMERS_MANAGE_NOTES,
            self::CONFIGURATION_VIEW,
            self::CONFIGURATION_MANAGE,
            self::CMS_VIEW,
            self::CMS_MANAGE,
            self::CMS_PUBLISH,
            self::SETTINGS_VIEW,
            self::SETTINGS_MANAGE,
            self::REPORTS_VIEW,
            self::REPORTS_EXPORT,
            self::ANALYTICS_VIEW,
            self::ANALYTICS_EXPORT,
            self::PROFIT_REPORTS_VIEW,
            self::GROWTH_VIEW,
            self::GROWTH_MANAGE,
            self::SUPPLIERS_VIEW,
            self::SUPPLIERS_CREATE,
            self::SUPPLIERS_UPDATE,
            self::SUPPLIERS_DELETE,
            self::PROCUREMENT_VIEW,
            self::PROCUREMENT_CREATE,
            self::PROCUREMENT_UPDATE,
            self::PROCUREMENT_DELETE,
            self::PURCHASE_ORDERS_VIEW,
            self::PURCHASE_ORDERS_CREATE,
            self::PURCHASE_ORDERS_UPDATE,
            self::PURCHASE_ORDERS_APPROVE,
            self::PURCHASE_ORDERS_RECEIVE,
            self::PURCHASE_ORDERS_CANCEL,
            self::WAREHOUSE_VIEW,
            self::WAREHOUSE_JOBS_VIEW,
            self::WAREHOUSE_JOBS_CREATE,
            self::WAREHOUSE_JOBS_UPDATE,
            self::WAREHOUSE_JOBS_COMPLETE,
            self::WAREHOUSE_JOBS_DELETE,
            self::PROMOTIONS_VIEW,
            self::PROMOTIONS_CREATE,
            self::PROMOTIONS_UPDATE,
            self::PROMOTIONS_DELETE,
            self::LOYALTY_VIEW,
            self::LOYALTY_MANAGE,
            self::NOTIFICATIONS_TEMPLATES_VIEW,
            self::NOTIFICATIONS_TEMPLATES_MANAGE,
            self::ACTIVITY_LOGS_VIEW,
        ];
    }

    public static function isKnown(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    /**
     * Human-readable labels for seeding / admin UI.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::all() as $slug) {
            $labels[$slug] = str_replace(['.', '_'], [' / ', ' '], $slug);
        }

        return $labels;
    }
}
