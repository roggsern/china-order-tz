<?php

namespace App\Enums;

enum SupportTicketCategory: string
{
    case OrderIssue = 'order_issue';
    case PaymentIssue = 'payment_issue';
    case DeliveryIssue = 'delivery_issue';
    case ProductIssue = 'product_issue';
    case ReturnIssue = 'return_issue';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::OrderIssue => 'Order Issue',
            self::PaymentIssue => 'Payment Issue',
            self::DeliveryIssue => 'Delivery Issue',
            self::ProductIssue => 'Product Issue',
            self::ReturnIssue => 'Return Issue',
            self::General => 'General',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
