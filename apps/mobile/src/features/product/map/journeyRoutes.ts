import type { CommerceJourney } from '@/src/shared/types/commerce';
import { isCommerceJourney } from '@/src/shared/types/commerce';
import type { ProductDetailParams } from '../models/types';

/**
 * Journey-separated catalog routes (Contract v1).
 * Never mix CHINA_IMPORT and TZ_LOCAL endpoints.
 */
export function chinaProductsPath(): string {
  return '/storefront/china/products';
}

export function chinaCategoriesPath(): string {
  return '/storefront/china/categories';
}

export function tzStoresPath(): string {
  return '/storefront/tz/stores';
}

export function tzStoreCategoriesPath(storeSlug: string): string {
  return `/storefront/tz/stores/${encodeURIComponent(storeSlug)}/categories`;
}

export function tzStoreProductsPath(storeSlug: string): string {
  return `/storefront/tz/stores/${encodeURIComponent(storeSlug)}/products`;
}

export function tzStoreProductDetailPath(storeSlug: string, productKey: string): string {
  return `/storefront/tz/stores/${encodeURIComponent(storeSlug)}/products/${encodeURIComponent(productKey)}`;
}

export function sharedProductDetailPath(productKey: string): string {
  return `/products/${encodeURIComponent(productKey)}`;
}

export function sharedProductConfigurationPath(productKey: string): string {
  return `/products/${encodeURIComponent(productKey)}/configuration`;
}

export function sharedProductQuotePath(productKey: string): string {
  return `/products/${encodeURIComponent(productKey)}/quote`;
}

export function resolveProductDetailPath(params: ProductDetailParams): string {
  if (params.journey === 'TZ_LOCAL') {
    if (!params.storeSlug) {
      throw new Error('TZ_LOCAL product detail requires storeSlug');
    }
    return tzStoreProductDetailPath(params.storeSlug, params.productKey);
  }
  return sharedProductDetailPath(params.productKey);
}

export function buildProductHref(params: {
  slug: string;
  journey: CommerceJourney;
  storeSlug?: string | null;
}): string {
  const query = new URLSearchParams();
  query.set('journey', params.journey);
  if (params.journey === 'TZ_LOCAL' && params.storeSlug) {
    query.set('store', params.storeSlug);
  }
  return `/(app)/product/${encodeURIComponent(params.slug)}?${query.toString()}`;
}

export function parseJourneyParam(value: unknown, fallback: CommerceJourney): CommerceJourney {
  if (typeof value === 'string' && isCommerceJourney(value)) {
    return value;
  }
  return fallback;
}
