import { getBrandBySlug } from "@/lib/catalog/brands";
import type { ProductOrigin } from "@/lib/types/catalog";

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

export function buildSearchCategoryHref(slug: string): string {
  const brand = getBrandBySlug(slug);
  if (brand) {
    const firstSubcategory = brand.subcategories[0];
    return firstSubcategory
      ? `/brand/${slug}/${firstSubcategory.slug}`
      : buildProductSearchHref("", "tz");
  }

  return `/categories/${slug}`;
}
