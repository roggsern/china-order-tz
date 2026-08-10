import { getBrandBySlug } from "@/lib/catalog/brands";
import type { ProductOrigin } from "@/lib/types/catalog";
import type { SearchMarketplaceScope } from "@/components/search/SearchMarketplaceScope";

/**
 * Header search always defaults to All (global marketplace).
 * Page URL / pathname must not change the default corpus — All / China / TZ
 * tabs are explicit user filters only.
 *
 * Optional input is accepted for call-site compatibility and ignored.
 */
export function resolveDefaultSearchMarketplaceScope(_input?: {
  origin?: string | null;
  pathname?: string | null;
}): SearchMarketplaceScope {
  return "all";
}

export function buildProductSearchHref(query: string, origin?: ProductOrigin): string {
  const params = new URLSearchParams();

  if (query.trim()) {
    params.set("q", query.trim());
  }

  if (origin) {
    params.set("origin", origin);
  }

  const queryString = params.toString();
  return queryString ? `/products?${queryString}` : "/products";
}

/**
 * Category/brand suggestion destinations.
 * China scope must keep CHINA_IMPORT listing context (origin + category/brand).
 */
export function buildSearchCategoryHref(slug: string, origin?: ProductOrigin): string {
  if (origin === "china") {
    return `/products?origin=china&category=${encodeURIComponent(slug)}`;
  }

  if (origin === "tz") {
    const brand = getBrandBySlug(slug);
    if (brand) {
      return `/buy-from-tz/${brand.slug}`;
    }

    return `/products?origin=tz&category=${encodeURIComponent(slug)}`;
  }

  const brand = getBrandBySlug(slug);
  if (brand) {
    return `/buy-from-tz/${brand.slug}`;
  }

  return `/categories/${slug}`;
}

/** Brand suggestion destinations (China keeps storefront listing context). */
export function buildSearchBrandHref(slug: string, origin?: ProductOrigin): string {
  if (origin === "china") {
    return `/products?origin=china&brand=${encodeURIComponent(slug)}`;
  }

  const brand = getBrandBySlug(slug);
  if (brand) {
    return `/buy-from-tz/${brand.slug}`;
  }

  if (origin === "tz") {
    return `/products?origin=tz&brand=${encodeURIComponent(slug)}`;
  }

  return `/products?brand=${encodeURIComponent(slug)}`;
}

/** TZ store suggestion destinations. */
export function buildSearchStoreHref(slug: string): string {
  return `/buy-from-tz/${encodeURIComponent(slug)}`;
}
