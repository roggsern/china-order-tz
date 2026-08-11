import type { CommerceJourney } from '@/src/shared/types/commerce';
import type { LiveHomepageCommerce } from '../api/fetchLiveHomepageCommerce';
import type {
  HomepageMeta,
  HomepageProductCard,
  HomepageViewModel,
  RenderableHomepageSection,
} from '../models/types';
import {
  buildPresentationHeroSlides,
  buildPresentationTrustItems,
} from '../utils/presentationFallback';

function isProductRail(
  section: RenderableHomepageSection,
): section is Extract<
  RenderableHomepageSection,
  { kind: 'FEATURED_PRODUCTS' | 'NEW_ARRIVALS' | 'BEST_SELLERS' }
> {
  return (
    section.kind === 'FEATURED_PRODUCTS' ||
    section.kind === 'NEW_ARRIVALS' ||
    section.kind === 'BEST_SELLERS'
  );
}

function productMatchesJourney(
  product: HomepageProductCard,
  journey: CommerceJourney,
): boolean {
  const code = product.commerceChannelCode?.trim();
  if (!code) return true;
  return code === journey;
}

function filterProductsForJourney(
  products: HomepageProductCard[],
  journey: CommerceJourney,
): HomepageProductCard[] {
  return products.filter((product) => productMatchesJourney(product, journey));
}

/**
 * Drop cross-journey CMS blocks when the resolved layout is GLOBAL
 * (backend fallback) so CHINA_IMPORT / TZ_LOCAL stay isolated.
 */
export function filterSectionsForJourney(
  sections: RenderableHomepageSection[],
  journey: CommerceJourney,
  meta: HomepageMeta,
  layoutCommerceContext?: string | null,
): RenderableHomepageSection[] {
  const globalLayout =
    meta.used_global_fallback === true ||
    (layoutCommerceContext ?? meta.resolved_commerce_context ?? '').toUpperCase() ===
      'GLOBAL';

  return sections
    .map((section) => {
      if (isProductRail(section)) {
        return {
          ...section,
          products: filterProductsForJourney(section.products, journey),
        };
      }
      return section;
    })
    .filter((section) => {
      if (journey === 'CHINA_IMPORT' && section.kind === 'SHOP_BY_STORE') {
        return false;
      }
      if (
        globalLayout &&
        journey === 'TZ_LOCAL' &&
        section.kind === 'FEATURED_CATEGORIES'
      ) {
        // GLOBAL category rails are China-oriented; TZ uses live stores instead.
        return false;
      }
      if (isProductRail(section) && section.products.length === 0) {
        return false;
      }
      if (section.kind === 'FEATURED_CATEGORIES' && section.categories.length === 0) {
        return false;
      }
      if (section.kind === 'SHOP_BY_STORE' && section.stores.length === 0) {
        return false;
      }
      if (section.kind === 'HERO' && section.slides.length === 0) {
        return false;
      }
      return true;
    });
}

function insertAfterHero(
  sections: RenderableHomepageSection[],
  next: RenderableHomepageSection,
): RenderableHomepageSection[] {
  const heroIndex = sections.findIndex((section) => section.kind === 'HERO');
  if (heroIndex < 0) {
    return [next, ...sections];
  }
  return [
    ...sections.slice(0, heroIndex + 1),
    next,
    ...sections.slice(heroIndex + 1),
  ];
}

function hasKind(
  sections: RenderableHomepageSection[],
  kind: RenderableHomepageSection['kind'],
): boolean {
  return sections.some((section) => section.kind === kind);
}

/**
 * Compose CMS homepage + live commerce + presentation fallbacks.
 * Prefer CMS content when present; fill gaps with live APIs; never invent catalog data.
 */
export function composeHomepageViewModel(params: {
  cms: HomepageViewModel;
  live: LiveHomepageCommerce;
  journey: CommerceJourney;
}): HomepageViewModel {
  const { cms, live, journey } = params;

  let sections = filterSectionsForJourney(
    cms.sections,
    journey,
    cms.meta,
    cms.layout?.commerce_context,
  );

  const hasHero = sections.some(
    (section) => section.kind === 'HERO' && section.slides.length > 0,
  );
  if (!hasHero) {
    sections = [
      {
        kind: 'HERO',
        key: `presentation-hero:${journey}`,
        title: null,
        subtitle: null,
        slides: buildPresentationHeroSlides(journey),
      },
      ...sections.filter((section) => section.kind !== 'HERO'),
    ];
  }

  if (journey === 'TZ_LOCAL' && !hasKind(sections, 'SHOP_BY_STORE') && live.stores.length > 0) {
    sections = insertAfterHero(sections, {
      kind: 'SHOP_BY_STORE',
      key: 'live:shop-by-store',
      title: 'Shop by Store',
      subtitle: 'Trusted Tanzanian storefronts',
      stores: live.stores,
    });
  }

  if (
    journey === 'CHINA_IMPORT' &&
    !hasKind(sections, 'FEATURED_CATEGORIES') &&
    live.categories.length > 0
  ) {
    sections = insertAfterHero(sections, {
      kind: 'FEATURED_CATEGORIES',
      key: 'live:categories',
      title: 'Shop by category',
      subtitle: 'Browse China import departments',
      categories: live.categories,
    });
  }

  const hasProducts = sections.some(
    (section) => isProductRail(section) && section.products.length > 0,
  );
  if (!hasProducts) {
    const featured = filterProductsForJourney(live.featuredProducts, journey);
    const catalog = filterProductsForJourney(live.catalogProducts, journey);
    const products = featured.length > 0 ? featured : catalog;
    if (products.length > 0) {
      sections.push({
        kind: featured.length > 0 ? 'FEATURED_PRODUCTS' : 'NEW_ARRIVALS',
        key: featured.length > 0 ? 'live:featured' : 'live:new-arrivals',
        title: featured.length > 0 ? 'Featured products' : 'New arrivals',
        subtitle:
          journey === 'TZ_LOCAL'
            ? 'From Tanzanian stores'
            : 'From the China import catalog',
        products,
      });
    }
  }

  if (!hasKind(sections, 'TRUST')) {
    sections.push({
      kind: 'TRUST',
      key: 'presentation:trust',
      title: 'Why shop with CHINA ORDER TZ',
      subtitle: null,
      items: buildPresentationTrustItems(),
    });
  }

  return {
    layout: cms.layout,
    meta: cms.meta,
    sections,
  };
}

export function emptyCmsHomepageView(
  journey: CommerceJourney,
): HomepageViewModel {
  return {
    layout: null,
    meta: {
      commerce_context: journey,
      resolved_commerce_context: journey,
      allow_global_fallback: true,
      used_global_fallback: false,
      campaign: null,
    },
    sections: [],
  };
}
