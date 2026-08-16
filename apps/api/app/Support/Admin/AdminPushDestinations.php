<?php

namespace App\Support\Admin;

/**
 * Stable admin-mobile push navigation destinations.
 * Backend is the authority — mobile maps these to Expo Router routes only.
 */
final class AdminPushDestinations
{
    public const DASHBOARD = 'admin.dashboard';

    public const ORDERS = 'admin.orders';

    public const ORDER_DETAIL = 'admin.order_detail';

    public const SUPPORT = 'admin.support';

    public const SUPPORT_TICKET = 'admin.support_ticket';

    public const LOW_STOCK = 'admin.low_stock';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DASHBOARD,
            self::ORDERS,
            self::ORDER_DETAIL,
            self::SUPPORT,
            self::SUPPORT_TICKET,
            self::LOW_STOCK,
        ];
    }

    public static function isKnown(string $destination): bool
    {
        return in_array($destination, self::all(), true);
    }
}
