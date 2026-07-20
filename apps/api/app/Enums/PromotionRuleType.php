<?php

namespace App\Enums;

enum PromotionRuleType: string
{
    case Product = 'product';
    case Variant = 'variant';
    case Category = 'category';
    case CommerceChannel = 'commerce_channel';
    case CustomerTag = 'customer_tag';
    case CartTotal = 'cart_total';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Variant => 'Variant',
            self::Category => 'Category',
            self::CommerceChannel => 'Commerce Channel',
            self::CustomerTag => 'Customer Tag',
            self::CartTotal => 'Cart Total',
        };
    }
}
