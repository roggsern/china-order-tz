<?php

namespace App\Support\Catalog;

/**
 * Canonical mobile-accessory placement for CHINA_IMPORT.
 *
 * Domain model (not a literal Electronics department):
 * Phones & Tablets (department)
 * → Phone Accessories (category)
 * → Power Banks (leaf subcategory)
 * → Power Bank (catalog product type)
 */
final class MobileAccessoriesTaxonomy
{
    public const PHONES_TABLETS_DEPARTMENT_SLUG = 'phones-tablets';

    public const CONSUMER_ELECTRONICS_DEPARTMENT_SLUG = 'consumer-electronics';

    public const CANONICAL_ACCESSORIES_SLUG = 'phones-tablets-phone-accessories';

    public const CANONICAL_POWER_BANKS_SLUG = 'phones-tablets-phone-accessories-power-banks';

    public const CANONICAL_CHARGERS_SLUG = 'phones-tablets-phone-accessories-chargers';

    public const CANONICAL_POWER_BANK_TYPE_SLUG = 'phones-tablets-phone-accessories-power-banks-power-bank';

    public const CANONICAL_ACCESSORIES_NAME = 'Phone Accessories';

    public const POWER_BANKS_NAME = 'Power Banks';

    public const MOBILE_ACCESSORIES_SLUG = 'mobile-accessories';

    /**
     * Competing CPT names seen in operator-created Consumer Electronics trees.
     *
     * @var list<string>
     */
    public const COMPETING_POWER_BANK_TYPE_NAMES = [
        'Power Bank',
        'Power Banks',
    ];

    /**
     * Competing CPT slugs that must never be (re)created.
     *
     * @var list<string>
     */
    public const FORBIDDEN_POWER_BANK_TYPE_SLUGS = [
        'mobile-accessories-power-banks',
        'consumer-electronics-power-banks-power-bank',
        'consumer-electronics-power-banks-power-banks',
    ];

    /**
     * Competing locations that seed/import must never recreate.
     *
     * @var list<string>
     */
    public const FORBIDDEN_POWER_BANK_SLUGS = [
        'consumer-electronics-power-banks',
        'electronics-power-banks',
        'phones-tablets-power-banks',
    ];

    /**
     * Mobile-accessory names that must not be seeded under Consumer Electronics.
     *
     * @var list<string>
     */
    public const FORBIDDEN_CONSUMER_ELECTRONICS_NAMES = [
        'Power Banks',
        'Phone Accessories',
        'Mobile Accessories',
        'Chargers',
        'Phone Cases',
        'Screen Protectors',
        'Cables',
        'Wireless Chargers',
    ];

    public static function isForbiddenDepartmentCategory(string $departmentSlug, string $name): bool
    {
        if ($departmentSlug !== self::CONSUMER_ELECTRONICS_DEPARTMENT_SLUG) {
            return false;
        }

        return in_array($name, self::FORBIDDEN_CONSUMER_ELECTRONICS_NAMES, true);
    }

    public static function isForbiddenPowerBankSlug(string $slug): bool
    {
        return in_array($slug, self::FORBIDDEN_POWER_BANK_SLUGS, true);
    }

    public static function isCompetingPowerBankTypeName(string $name): bool
    {
        return in_array($name, self::COMPETING_POWER_BANK_TYPE_NAMES, true);
    }

    public static function isForbiddenPowerBankTypeSlug(string $slug): bool
    {
        if ($slug === self::CANONICAL_POWER_BANK_TYPE_SLUG) {
            return false;
        }

        if (in_array($slug, self::FORBIDDEN_POWER_BANK_TYPE_SLUGS, true)) {
            return true;
        }

        return str_ends_with($slug, '-power-bank') || str_ends_with($slug, '-power-banks');
    }
}
