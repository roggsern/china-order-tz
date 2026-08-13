import { getProductCheckoutSummary, getProducts } from "@/lib/api/products";
import { mapApiProductCardToCatalogProduct } from "@/lib/catalog/map-api-product";
import type { Product } from "@/lib/types/catalog";

/**
 * Loads the live Customer API catalog for cart validation (client-side via BFF).
 * Paginates through all product pages so checkout can resolve API-backed cart items.
 */
export async function fetchClientCatalogProducts(): Promise<Product[]> {
  const perPage = 100;
  const first = await getProducts({ page: 1, per_page: perPage });
  const products = first.products.map(mapApiProductCardToCatalogProduct);

  for (let page = 2; page <= first.meta.last_page; page += 1) {
    const next = await getProducts({ page, per_page: perPage });
    products.push(...next.products.map(mapApiProductCardToCatalogProduct));
  }

  return products;
}

/**
 * Checkout/cart validation for a known cart — fetch listing-grade checkout summaries
 * in parallel instead of PDP show payloads.
 */
export async function fetchClientCatalogProductsForSlugs(
  slugs: string[],
): Promise<Product[]> {
  const unique = [...new Set(slugs.map((slug) => slug.trim()).filter(Boolean))];
  if (unique.length === 0) {
    return [];
  }

  const results = await Promise.all(
    unique.map(async (slug) => {
      try {
        const summary = await getProductCheckoutSummary(slug);
        return mapApiProductCardToCatalogProduct(summary);
      } catch {
        return null;
      }
    }),
  );

  return results.filter((product): product is Product => product !== null);
}
