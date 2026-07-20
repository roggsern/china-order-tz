<?php

namespace App\Enums\CMS;

/**
 * Hero CTA target types validated against existing platform capabilities.
 *
 * PAGE targets published stub pages (no public page API yet — frontend resolves slug).
 * CHINA_ORDER_FORM is a fixed app destination, not a database entity.
 */
enum CmsCtaTargetType: string
{
    case None = 'NONE';
    case Url = 'URL';
    case Product = 'PRODUCT';
    case Store = 'STORE';
    case Category = 'CATEGORY';
    case Brand = 'BRAND';
    case Promotion = 'PROMOTION';
    case Page = 'PAGE';
    case ChinaOrderForm = 'CHINA_ORDER_FORM';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Url => 'URL',
            self::Product => 'Product',
            self::Store => 'Store',
            self::Category => 'Category',
            self::Brand => 'Brand',
            self::Promotion => 'Promotion',
            self::Page => 'Page',
            self::ChinaOrderForm => 'China Order Form',
        };
    }

    public function requiresValue(): bool
    {
        return match ($this) {
            self::None, self::ChinaOrderForm => false,
            default => true,
        };
    }
}
