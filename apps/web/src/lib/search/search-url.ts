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

/**
 * Free-text search results destination (header Enter / View all / term chips).
 * Uses unified search page — not legacy /products?q=.
 */
export function buildUnifiedSearchHref(
  query: string,
  scope: SearchMarketplaceScope = "all",
): string {
  const params = new URLSearchParams();

  if (query.trim()) {
    params.set("q", query.trim());
  }

  params.set("scope", scope === "china" || scope === "tz" ? scope : "all");

  const queryString = params.toString();
  return queryString ? `/search?${queryString}` : "/search";
}

/** @deprecated Prefer buildUnifiedSearchHref — kept as alias for free-text search links. */
export function buildProductSearchHref(
  query: string,
  origin?: ProductOrigin | SearchMarketplaceScope,
): string {
  const scope: SearchMarketplaceScope =
    origin === "china" || origin === "tz" ? origin : "all";
  return buildUnifiedSearchHref(query, scope);
}

/** Heading helpers for the unified search results page. */
export function resolveSearchPageHeading(query: string): string {
  const trimmed = query.trim();
  return trimmed ? `Results for "${trimmed}"` : "Search";
}

export function resolveSearchPageScopeLabel(scope: SearchMarketplaceScope): string {
  if (scope === "china") return "China";
  if (scope === "tz") return "Buy from Dar";
  return "All marketplaces";
}

export function resolveSearchPageScope(
  raw?: string | null,
): SearchMarketplaceScope {
  if (raw === "china" || raw === "tz") {
    return raw;
  }
  return "all";
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
