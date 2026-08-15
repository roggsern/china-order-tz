/**
 * CMS homepage models aligned with Contract v1 / Laravel storefront resources.
 * Journey store uses CHINA_IMPORT | TZ_LOCAL only; GLOBAL is a homepage API context.
 */

export const HOMEPAGE_COMMERCE_CONTEXTS = ['GLOBAL', 'CHINA_IMPORT', 'TZ_LOCAL'] as const;
export type HomepageCommerceContext = (typeof HOMEPAGE_COMMERCE_CONTEXTS)[number];

export const HOMEPAGE_SECTION_TYPES = [
  'HERO',
  'HOMEPAGE_BANNER',
  'FLASH_DEALS',
  'FEATURED_COLLECTIONS',
  'SHOP_BY_STORE',
  'FEATURED_PRODUCTS',
  'FEATURED_BRANDS',
  'FEATURED_CATEGORIES',
  'MID_PAGE_ADVERTISEMENT',
  'NEW_ARRIVALS',
  'BEST_SELLERS',
  'WHY_CHOOSE_US',
  'TRUST_INDICATORS',
  'NEWSLETTER',
  'FOOTER_ADVERTISEMENT',
] as const;

export type HomepageSectionType = (typeof HOMEPAGE_SECTION_TYPES)[number] | (string & {});

export type HomepageMedia = {
  id?: string;
  url?: string | null;
  path?: string | null;
  display_url?: string | null;
  original_url?: string | null;
  alt_text?: string | null;
};

export type HomepageCta = {
  type?: string | null;
  label?: string | null;
  value?: string | null;
  url?: string | null;
};

export type HomepageHeroSlide = {
  id: string;
  headline: string | null;
  subheadline: string | null;
  eyebrow_text?: string | null;
  description?: string | null;
  desktop_media?: HomepageMedia | null;
  mobile_media?: HomepageMedia | null;
  /** Bundled owned asset (require) — presentation fallback only. */
  localImageSource?: number;
  primary_cta?: HomepageCta | null;
  secondary_cta?: HomepageCta | null;
  position: number;
};

export type HomepageFeaturedItem = {
  item_type: string;
  id: string;
  data: Record<string, unknown>;
};

export type HomepageFeaturedContent = {
  id: string;
  cms_homepage_section_id?: string;
  title: string | null;
  subtitle: string | null;
  source_type?: string | null;
  limit?: number;
  position: number;
  items?: HomepageFeaturedItem[];
};

export type HomepageSection = {
  id: string;
  cms_homepage_layout_id?: string;
  section_type: HomepageSectionType;
  title: string | null;
  subtitle: string | null;
  position: number;
  is_visible: boolean;
  configuration?: Record<string, unknown>;
  hero_slides?: HomepageHeroSlide[];
  featured_contents?: HomepageFeaturedContent[];
};

export type HomepageLayout = {
  id: string;
  name: string;
  slug: string;
  commerce_context: string;
  status: string;
  is_default: boolean;
  sections: HomepageSection[];
};

export type HomepageCampaignMeta = {
  id: string;
  name: string;
  slug: string;
  priority: number;
  promotion_ids?: string[];
};

export type HomepageMeta = {
  commerce_context: string;
  resolved_commerce_context?: string;
  allow_global_fallback?: boolean;
  used_global_fallback?: boolean;
  campaign?: HomepageCampaignMeta | null;
  message?: string;
};

export type HomepageProductCard = {
  id: string;
  slug: string;
  name: string;
  price: string | number | null;
  compareAtPrice?: string | number | null;
  imageUrl: string | null;
  commerceChannelCode?: string | null;
  /** TZ store slug when CMS/API provides store context — never invented. */
  storeSlug?: string | null;
};

/** Category / collection tile from CMS FEATURED_CATEGORIES or FEATURED_COLLECTIONS. */
export type HomepageCategoryCard = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  imageUrl: string | null;
};

/** Store tile from CMS SHOP_BY_STORE. */
export type HomepageStoreCard = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  imageUrl: string | null;
};

/** Trust / why-choose row from CMS copy or featured items — never invented. */
export type HomepageTrustItem = {
  id: string;
  title: string;
  description: string | null;
};

/** UI-ready section after mapping (unknown CMS types dropped). */
export type RenderableHomepageSection =
  | {
      kind: 'HERO';
      key: string;
      title: string | null;
      subtitle: string | null;
      slides: HomepageHeroSlide[];
    }
  | {
      kind: 'FEATURED_PRODUCTS' | 'NEW_ARRIVALS' | 'BEST_SELLERS';
      key: string;
      title: string | null;
      subtitle: string | null;
      products: HomepageProductCard[];
    }
  | {
      kind: 'FEATURED_CATEGORIES';
      key: string;
      title: string | null;
      subtitle: string | null;
      categories: HomepageCategoryCard[];
    }
  | {
      kind: 'SHOP_BY_STORE';
      key: string;
      title: string | null;
      subtitle: string | null;
      stores: HomepageStoreCard[];
    }
  | {
      kind: 'TRUST';
      key: string;
      title: string | null;
      subtitle: string | null;
      items: HomepageTrustItem[];
    }
  | {
      kind: 'CAMPAIGN';
      key: string;
      campaign: HomepageCampaignMeta;
    };

export type HomepageViewModel = {
  layout: HomepageLayout | null;
  meta: HomepageMeta;
  sections: RenderableHomepageSection[];
};
