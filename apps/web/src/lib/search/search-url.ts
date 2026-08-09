import { getBrandBySlug } from "@/lib/catalog/brands";
import type { ProductOrigin } from "@/lib/types/catalog";
import type { SearchMarketplaceScope } from "@/components/search/SearchMarketplaceScope";

/**
 * Default search marketplace scope from the current URL / route.
 * Used only for initial defaults — not after the user manually switches tabs.
 */
export function resolveDefaultSearchMarketplaceScope(input: {
  origin?: string | null;
  pathname?: string | null;
}): SearchMarketplaceScope {
  if (input.origin === "china" || input.origin === "tz") {
    return input.origin;
  }

  if (input.pathname?.startsWith("/buy-from-tz")) {
    return "tz";
  }

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
