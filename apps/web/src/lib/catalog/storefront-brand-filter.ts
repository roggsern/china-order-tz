/**
 * Resolves which storefront brands endpoint ProductFilters should use.
 * China listings use category-scoped China storefront brands; other contexts keep the catalog brands list.
 */
export type StorefrontBrandQuery =
  | { source: "china"; categorySlug?: string }
  | { source: "catalog"; categoryId?: string; withProducts?: boolean };

export function resolveStorefrontBrandQuery(input: {
  origin?: string | null;
  categorySlug?: string | null;
}): StorefrontBrandQuery {
  if (input.origin === "china") {
    const categorySlug = input.categorySlug?.trim();

    return {
      source: "china",
      categorySlug: categorySlug ? categorySlug : undefined,
    };
  }

  return { source: "catalog" };
}
