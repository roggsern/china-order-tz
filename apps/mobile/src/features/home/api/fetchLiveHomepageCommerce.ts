import type { CommerceJourney } from '@/src/shared/types/commerce';
import {
  fetchChinaCategories,
  fetchChinaProducts,
  fetchTzProducts,
  fetchTzStores,
} from '@/src/features/product';
import {
  mapCatalogCategoryToHomepageCard,
  mapCatalogProductToHomepageCard,
  mapCatalogStoreToHomepageCard,
} from '../map/mapLiveCommerce';
import type {
  HomepageCategoryCard,
  HomepageProductCard,
  HomepageStoreCard,
} from '../models/types';

export type LiveHomepageCommerce = {
  categories: HomepageCategoryCard[];
  stores: HomepageStoreCard[];
  featuredProducts: HomepageProductCard[];
  catalogProducts: HomepageProductCard[];
};

export const EMPTY_LIVE_HOMEPAGE_COMMERCE: LiveHomepageCommerce = {
  categories: [],
  stores: [],
  featuredProducts: [],
  catalogProducts: [],
};

/**
 * Journey-scoped live commerce fills for Home (existing catalog APIs only).
 * Does not invent ranking — featured flag when API supports it; otherwise list page 1.
 */
export async function fetchLiveHomepageCommerce(
  journey: CommerceJourney,
): Promise<LiveHomepageCommerce> {
  if (journey === 'TZ_LOCAL') {
    const stores = await fetchTzStores().catch(() => []);
    const mappedStores = stores
      .filter((store) => store.isActive !== false)
      .map(mapCatalogStoreToHomepageCard);

    const primaryStore = mappedStores[0]?.slug ?? null;
    const productsResult = primaryStore
      ? await fetchTzProducts(primaryStore, { page: 1, perPage: 8 }).catch(
          () => null,
        )
      : null;

    const catalogProducts = (productsResult?.products ?? []).map(
      mapCatalogProductToHomepageCard,
    );

    return {
      categories: [],
      stores: mappedStores,
      featuredProducts: catalogProducts,
      catalogProducts,
    };
  }

  const [categories, featured, catalog] = await Promise.all([
    fetchChinaCategories().catch(() => []),
    fetchChinaProducts({ featured: true, page: 1, perPage: 8 }).catch(() => null),
    fetchChinaProducts({ page: 1, perPage: 8 }).catch(() => null),
  ]);

  const featuredProducts = (featured?.products ?? []).map(
    mapCatalogProductToHomepageCard,
  );
  const catalogProducts = (catalog?.products ?? []).map(
    mapCatalogProductToHomepageCard,
  );

  return {
    categories: categories.slice(0, 12).map(mapCatalogCategoryToHomepageCard),
    stores: [],
    featuredProducts:
      featuredProducts.length > 0 ? featuredProducts : catalogProducts,
    catalogProducts,
  };
}

export function liveHomepageHasContent(live: LiveHomepageCommerce): boolean {
  return (
    live.categories.length > 0 ||
    live.stores.length > 0 ||
    live.featuredProducts.length > 0 ||
    live.catalogProducts.length > 0
  );
}
