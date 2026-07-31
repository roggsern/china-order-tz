<?php

namespace App\Support\Admin;

final class PermissionRiskClassifier
{
    /**
     * @var list<string>
     */
    private const HIGH_RISK_SLUGS = [
        AdminPermissions::ADMINS_CREATE,
        AdminPermissions::ADMINS_UPDATE,
        AdminPermissions::ADMINS_ACTIVATE,
        AdminPermissions::ADMINS_DEACTIVATE,
        AdminPermissions::ADMINS_ASSIGN_ROLES,
        AdminPermissions::ROLES_MANAGE_PERMISSIONS,
        AdminPermissions::ORDERS_MARK_PAID,
        AdminPermissions::ORDERS_CANCEL,
        AdminPermissions::PAYMENTS_REFUND,
        AdminPermissions::PAYMENTS_RECONCILE,
        AdminPermissions::PAYMENTS_MANAGE_MANUAL,
        AdminPermissions::PAYMENTS_CONFIG_MANAGE,
        AdminPermissions::RETURNS_REFUND,
        AdminPermissions::REFUNDS_MANAGE,
        AdminPermissions::REFUNDS_APPROVE,
        AdminPermissions::SETTINGS_MANAGE,
        AdminPermissions::FEATURES_MANAGE,
        AdminPermissions::STORES_CREATE,
        AdminPermissions::STORES_UPDATE,
        AdminPermissions::STORES_MANAGE,
        AdminPermissions::SHIPPING_MANAGE,
        AdminPermissions::NOTIFICATIONS_MANAGE,
        AdminPermissions::CONFIGURATION_MANAGE,
        AdminPermissions::CATALOG_DELETE,
        AdminPermissions::WAREHOUSE_JOBS_DELETE,
        AdminPermissions::SUPPLIERS_DELETE,
        AdminPermissions::PROCUREMENT_DELETE,
        AdminPermissions::PROMOTIONS_DELETE,
    ];

    public static function classify(string $slug): PermissionRiskTier
    {
        if (in_array($slug, self::HIGH_RISK_SLUGS, true)) {
            return PermissionRiskTier::High;
        }

        if (str_ends_with($slug, '.view') || in_array($slug, [
            AdminPermissions::REPORTS_EXPORT,
            AdminPermissions::ANALYTICS_EXPORT,
        ], true)) {
            return PermissionRiskTier::Low;
        }

        if (str_ends_with($slug, '.delete')) {
            return PermissionRiskTier::High;
        }

        return PermissionRiskTier::Medium;
    }
}
