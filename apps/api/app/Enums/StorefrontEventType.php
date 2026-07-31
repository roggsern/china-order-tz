<?php

namespace App\Enums;

enum StorefrontEventType: string
{
    case PageView = 'page_view';
    case ProductViewed = 'product_viewed';
    case SearchPerformed = 'search_performed';
    case AddToCart = 'add_to_cart';
    case CheckoutStarted = 'checkout_started';
    case PaymentStarted = 'payment_started';
    case OrderCompleted = 'order_completed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public static function clientValues(): array
    {
        return array_values(array_filter(
            self::values(),
            static fn (string $value) => $value !== self::OrderCompleted->value,
        ));
    }
}
