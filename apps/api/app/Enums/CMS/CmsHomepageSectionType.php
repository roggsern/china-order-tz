<?php

namespace App\Enums\CMS;

enum CmsHomepageSectionType: string
{
    case Hero = 'HERO';
    case HomepageBanner = 'HOMEPAGE_BANNER';
    case FlashDeals = 'FLASH_DEALS';
    case FeaturedCollections = 'FEATURED_COLLECTIONS';
    case ShopByStore = 'SHOP_BY_STORE';
    case FeaturedProducts = 'FEATURED_PRODUCTS';
    case FeaturedBrands = 'FEATURED_BRANDS';
    case FeaturedCategories = 'FEATURED_CATEGORIES';
    case MidPageAdvertisement = 'MID_PAGE_ADVERTISEMENT';
    case NewArrivals = 'NEW_ARRIVALS';
    case BestSellers = 'BEST_SELLERS';
    case WhyChooseUs = 'WHY_CHOOSE_US';
    case TrustIndicators = 'TRUST_INDICATORS';
    case Newsletter = 'NEWSLETTER';
    case FooterAdvertisement = 'FOOTER_ADVERTISEMENT';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::HomepageBanner => 'Homepage Banner',
            self::FlashDeals => 'Flash Deals',
            self::FeaturedCollections => 'Featured Collections',
            self::ShopByStore => 'Shop By Store',
            self::FeaturedProducts => 'Featured Products',
            self::FeaturedBrands => 'Featured Brands',
            self::FeaturedCategories => 'Featured Categories',
            self::MidPageAdvertisement => 'Mid-Page Advertisement',
            self::NewArrivals => 'New Arrivals',
            self::BestSellers => 'Best Sellers',
            self::WhyChooseUs => 'Why Choose Us',
            self::TrustIndicators => 'Trust Indicators',
            self::Newsletter => 'Newsletter',
            self::FooterAdvertisement => 'Footer Advertisement',
        };
    }

    /**
     * Sections that may own CmsFeaturedContent blocks (not HERO).
     */
    public function supportsFeaturedContent(): bool
    {
        return in_array($this, [
            self::FeaturedProducts,
            self::FeaturedCollections,
            self::FeaturedBrands,
            self::FeaturedCategories,
            self::ShopByStore,
            self::BestSellers,
            self::NewArrivals,
            self::FlashDeals,
        ], true);
    }
}
