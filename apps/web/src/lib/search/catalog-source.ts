/**
 * Live search catalog — Customer API via BFF only.
 * Never reads seed products, localStorage admin catalog, or hardcoded demo terms.
 */

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
  };
}

/** Fetch products matching `search` from the live catalog API. */
export async function fetchLiveSearchProducts(search: string): Promise<Product[]> {
  const trimmed = search.trim();
  if (!trimmed) {
    return [];
  }

  const result = await getProducts({
    search: trimmed,
    page: 1,
    per_page: Math.max(MAX_PRODUCT_RESULTS * 3, 24),
  });

  return (result.products ?? []).map(mapApiProductCardToCatalogProduct);
}

/** Fetch live categories for search suggestions (no seed/fallback). */
export async function fetchLiveSearchCategories(): Promise<Category[]> {
  const categories = await getCategories();
  return (categories ?? []).map(mapApiCategoryToSearchCategory);
}

/** Fetch live brands as category-shaped suggestions. */
export async function fetchLiveSearchBrands(): Promise<Category[]> {
  const brands = await getBrands();
  return (brands ?? []).map(mapApiBrandToSearchCategory);
}

/**
 * Load live products + taxonomy for a search query.
 * Returns empty collections on empty query or API failure (caller shows empty state).
 */
export async function fetchLiveSearchCatalog(
  search: string,
  options?: { includeTaxonomy?: boolean },
): Promise<LiveSearchCatalog> {
  const trimmed = search.trim();
  if (!trimmed) {
    return { products: [], categories: [], brands: [] };
  }

  const includeTaxonomy = options?.includeTaxonomy !== false;

  const [products, categories, brands] = await Promise.all([
    fetchLiveSearchProducts(trimmed),
    includeTaxonomy ? fetchLiveSearchCategories().catch(() => []) : Promise.resolve([]),
    includeTaxonomy ? fetchLiveSearchBrands().catch(() => []) : Promise.resolve([]),
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
