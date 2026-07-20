import { cache } from "react";
import { getCatalogCategories } from "@/lib/catalog/categories";
import {
  getFeaturedProducts,
  getNewArrivalProducts,
  getProductsPage,
  sortProducts,
} from "@/lib/catalog/products";
import type { Product, ProductOrigin } from "@/lib/types/catalog";

export const HOMEPAGE_EMPTY_PRODUCTS_MESSAGE = "No products available yet";

export const getHomeCategories = cache(async () => getCatalogCategories());

export const getHomeFeaturedProducts = cache(async (limit = 8) => getFeaturedProducts(limit));

export const getHomeNewArrivals = cache(async (limit = 8) => getNewArrivalProducts(limit));

export const getHomeNewArrivalsByOrigin = cache(
  async (origin: ProductOrigin, limit = 4): Promise<Product[]> => {
    const result = await getProductsPage({
      origin,
      per_page: Math.max(limit * 2, 12),
      page: 1,
    });
    return sortProducts(result.products, "newest").slice(0, limit);
  },
);

/**
 * Best-sellers heuristic until a dedicated purchase-rank API exists:
 * featured first, then rating / review signals from the catalog list.
 */
export const getHomeBestSellers = cache(async (limit = 8): Promise<Product[]> => {
  const featured = await getFeaturedProducts(limit);
  if (featured.length >= limit) {
    return featured.slice(0, limit).map((product) => ({
      ...product,
      badges: Array.from(new Set([...(product.badges || []), "BEST SELLER" as const])),
    }));
  }

  const result = await getProductsPage({
    per_page: Math.max(limit * 3, 24),
    page: 1,
  });

  const ranked = [...result.products].sort((left, right) => {
    if (Boolean(left.bestSeller) !== Boolean(right.bestSeller)) {
      return left.bestSeller ? -1 : 1;
    }
    if (left.featured !== right.featured) {
      return left.featured ? -1 : 1;
    }
    if (right.rating !== left.rating) {
      return right.rating - left.rating;
    }
    return (right.reviews || 0) - (left.reviews || 0);
  });

  return ranked.slice(0, limit).map((product) => ({
    ...product,
    badges: Array.from(new Set([...(product.badges || []), "BEST SELLER" as const])),
  }));
});

export const getHomeCategoryProductCount = cache(async (slug: string): Promise<number> => {
  const result = await getProductsPage({ category: slug, per_page: 1, page: 1 });
  return result.meta.total;
});

export async function getHomeCategoryProductCounts(
  slugs: string[],
): Promise<Record<string, number>> {
  const entries = await Promise.all(
    slugs.map(async (slug) => [slug, await getHomeCategoryProductCount(slug)] as const),
  );

  return Object.fromEntries(entries);
}
