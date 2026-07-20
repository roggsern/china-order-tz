<?php

namespace App\Enums\CMS;

enum CmsFeaturedSourceType: string
{
    case Manual = 'MANUAL';
    case BestSellers = 'BEST_SELLERS';
    case NewArrivals = 'NEW_ARRIVALS';
    case MostViewed = 'MOST_VIEWED';
    case Promotion = 'PROMOTION';
    case Category = 'CATEGORY';
    case Brand = 'BRAND';
    case Store = 'STORE';
    case Collection = 'COLLECTION';
    case SearchFilter = 'SEARCH_FILTER';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::BestSellers => 'Best Sellers',
            self::NewArrivals => 'New Arrivals',
            self::MostViewed => 'Most Viewed',
            self::Promotion => 'Promotion',
            self::Category => 'Category',
            self::Brand => 'Brand',
            self::Store => 'Store',
            self::Collection => 'Collection',
            self::SearchFilter => 'Search Filter',
        };
    }

    /**
     * Primary entity kind returned by the resolver for this source.
     */
    public function resolvedItemType(): string
    {
        return match ($this) {
            self::Store => 'STORE',
            self::Brand => 'BRAND',
            self::Collection => 'CATEGORY',
            self::Manual => 'MIXED',
            default => 'PRODUCT',
        };
    }
}
