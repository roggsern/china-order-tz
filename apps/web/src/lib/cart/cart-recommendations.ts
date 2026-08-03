import { isProductPurchaseUnavailable } from "@/lib/catalog/product-availability";
import type { Product } from "@/lib/types/catalog";

export type CartRecommendationCartItem = {
  productId: number;
  slug?: string;
  catalogProductId?: string;
};

export function isCartRecommendationProduct(product: Product): boolean {
  if (product.status !== "active") {
    return false;
  }

  if (isProductPurchaseUnavailable(product)) {
    return false;
  }

  return true;
}

export function filterCartRecommendationCatalog(catalog: Product[]): Product[] {
  return catalog.filter(isCartRecommendationProduct);
}

export function isProductInCart(
  product: Product,
  cartItems: CartRecommendationCartItem[],
): boolean {
  return cartItems.some(
    (item) =>
      item.productId === product.id ||
      (item.slug != null && item.slug.length > 0 && item.slug === product.slug) ||
      (item.catalogProductId != null &&
        item.catalogProductId.length > 0 &&
        item.catalogProductId === product.catalogProductId),
  );
}

function sortByFeaturedThenRating(left: Product, right: Product): number {
  if (left.featured !== right.featured) {
    return left.featured ? -1 : 1;
  }

  return right.rating - left.rating;
}

export function buildFrequentlyBoughtTogetherProducts(
  catalog: Product[],
  cartItems: CartRecommendationCartItem[],
  categorySlugs: string[],
  limit = 8,
): Product[] {
  const eligible = filterCartRecommendationCatalog(catalog);
  const cartProductIds = new Set(cartItems.map((item) => item.productId));
  const normalizedCategories = categorySlugs.filter(
    (slug) => slug && slug !== "uncategorized",
  );

  const byCategory = eligible.filter(
    (product) =>
      !cartProductIds.has(product.id) &&
      !isProductInCart(product, cartItems) &&
      normalizedCategories.includes(product.categorySlug),
  );

  const pool =
    byCategory.length > 0
      ? [...byCategory].reverse()
      : eligible
          .filter(
            (product) =>
              !cartProductIds.has(product.id) && !isProductInCart(product, cartItems),
          )
          .sort(sortByFeaturedThenRating);

  return pool.slice(0, limit);
}

export function buildCartRecommendedProducts(catalog: Product[], limit = 8): Product[] {
  return [...filterCartRecommendationCatalog(catalog)]
    .sort(sortByFeaturedThenRating)
    .slice(0, limit);
}
