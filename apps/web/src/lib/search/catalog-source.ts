/**
 * Live search catalog — Customer API via BFF only.
 * Never reads seed products, localStorage admin catalog, or hardcoded demo terms.
 */

import {
  getChinaStorefrontBrands,
  getChinaStorefrontProducts,
} from "@/lib/api/china-storefront";
import { getBrands, getCategories, getProducts } from "@/lib/api/products";
import { mapApiProductCardToCatalogProduct } from "@/lib/catalog/map-api-product";
import type { Category, Product, ProductOrigin } from "@/lib/types/catalog";
import { MAX_PRODUCT_RESULTS } from "@/lib/search/constants";

const DEFAULT_CATEGORY_GRADIENT = "from-zinc-200 via-zinc-100 to-zinc-300";
const DEFAULT_CATEGORY_ICON = "📦";

export type LiveSearchCatalog = {
  products: Product[];
  categories: Category[];
  brands: Category[];
};

export type LiveSearchProductSource = "china-storefront" | "catalog";
export type LiveSearchBrandSource = "china-storefront" | "catalog";

/** China scope uses the China storefront products API; All/TZ keep the generic catalog search. */
export function resolveLiveSearchProductSource(
  origin?: ProductOrigin,
): LiveSearchProductSource {
  return origin === "china" ? "china-storefront" : "catalog";
}

/** China scope uses product-relevant China brands (same source as China filters). */
export function resolveLiveSearchBrandSource(
  origin?: ProductOrigin,
): LiveSearchBrandSource {
  return origin === "china" ? "china-storefront" : "catalog";
}

function mapApiCategoryToSearchCategory(entry: {
  name: string;
  slug: string;
}): Category {
  return {
    slug: entry.slug,
    name: entry.name,
    description: entry.name,
    gradient: DEFAULT_CATEGORY_GRADIENT,
    icon: DEFAULT_CATEGORY_ICON,
  };
}

function mapApiBrandToSearchCategory(entry: {
  name: string;
  slug: string;
}): Category {
  return {
    slug: entry.slug,
    name: entry.name,
    description: `${entry.name} brand`,
    gradient: "from-amber-200 via-orange-100 to-rose-200",
    icon: "🏷",
    searchSuggestionType: "brand",
  };
}

/** Fetch products matching `search` from the live catalog API. */
export async function fetchLiveSearchProducts(
  search: string,
  origin?: ProductOrigin,
): Promise<Product[]> {
  const trimmed = search.trim();
  if (!trimmed) {
    return [];
  }

  const perPage = Math.max(MAX_PRODUCT_RESULTS * 3, 24);

  if (resolveLiveSearchProductSource(origin) === "china-storefront") {
    const products = await getChinaStorefrontProducts({
      search: trimmed,
      page: 1,
      per_page: perPage,
    });

    return (products ?? []).map(mapApiProductCardToCatalogProduct);
  }

  const result = await getProducts({
    search: trimmed,
    page: 1,
    per_page: perPage,
  });

  return (result.products ?? []).map(mapApiProductCardToCatalogProduct);
}

/** Fetch live categories for search suggestions (no seed/fallback). */
export async function fetchLiveSearchCategories(): Promise<Category[]> {
  const categories = await getCategories();
  return (categories ?? []).map(mapApiCategoryToSearchCategory);
}

/** Fetch live brands as category-shaped suggestions (China uses storefront brands). */
export async function fetchLiveSearchBrands(
  origin?: ProductOrigin,
): Promise<Category[]> {
  if (resolveLiveSearchBrandSource(origin) === "china-storefront") {
    const brands = await getChinaStorefrontBrands();
    return (brands ?? []).map(mapApiBrandToSearchCategory);
  }

  const brands = await getBrands();
  return (brands ?? []).map(mapApiBrandToSearchCategory);
}

/**
 * Load live products + taxonomy for a search query.
 * Returns empty collections on empty query or API failure (caller shows empty state).
 */
export async function fetchLiveSearchCatalog(
  search: string,
  options?: { includeTaxonomy?: boolean; origin?: ProductOrigin },
): Promise<LiveSearchCatalog> {
  const trimmed = search.trim();
  if (!trimmed) {
    return { products: [], categories: [], brands: [] };
  }

  const includeTaxonomy = options?.includeTaxonomy !== false;

  const [products, categories, brands] = await Promise.all([
    fetchLiveSearchProducts(trimmed, options?.origin),
    includeTaxonomy ? fetchLiveSearchCategories().catch(() => []) : Promise.resolve([]),
    includeTaxonomy
      ? fetchLiveSearchBrands(options?.origin).catch(() => [])
      : Promise.resolve([]),
  ]);

  return { products, categories, brands };
}

export function filterProductsByOrigin(
  products: Product[],
  origin?: ProductOrigin,
): Product[] {
  if (!origin) return products;
  return products.filter((product) => product.origin === origin);
}
