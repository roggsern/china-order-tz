import { mapApiProductCardToCatalogProduct } from "@/lib/catalog/map-api-product";
import type { ApiCatalogProductCard } from "@/lib/api/products";
import type {
  UnifiedSearchSuggestData,
  UnifiedSuggestBrand,
  UnifiedSuggestCategory,
  UnifiedSuggestProduct,
  UnifiedSuggestStore,
} from "@/lib/api/marketplace-search";
import type { Category, Product, ProductOrigin } from "@/lib/types/catalog";
import type { SearchMarketplaceScope } from "@/components/search/SearchMarketplaceScope";
import {
  buildUnifiedSearchHref,
  buildSearchBrandHref,
  buildSearchCategoryHref,
  buildSearchStoreHref,
} from "@/lib/search/search-url";
import type { SearchResults, SearchTermSuggestion } from "@/lib/search/types";
import {
  MAX_CATEGORY_RESULTS,
  MAX_PRODUCT_RESULTS,
  MAX_TERM_RESULTS,
} from "@/lib/search/constants";

const CATEGORY_GRADIENT = "from-zinc-200 via-zinc-100 to-zinc-300";
const BRAND_GRADIENT = "from-amber-200 via-orange-100 to-rose-200";
const STORE_GRADIENT = "from-emerald-200 via-teal-100 to-cyan-200";

function mapSuggestProduct(entry: UnifiedSuggestProduct): Product {
  const card = {
    ...entry,
    brand:
      entry.brand && typeof entry.brand === "object"
        ? {
            id: entry.brand.id ?? "",
            name: entry.brand.name ?? "",
            slug: entry.brand.slug ?? "",
          }
        : entry.brand,
  } as ApiCatalogProductCard;

  const product = mapApiProductCardToCatalogProduct(card);

  if (entry.marketplace === "china" || entry.marketplace === "tz") {
    product.origin = entry.marketplace;
  } else if (entry.commerce_channel_code) {
    product.commerceChannelCode = entry.commerce_channel_code;
  }

  if (entry.store?.name) {
    product.brand = product.brand || entry.store.name;
  }

  return product;
}

function mapBrandSuggestion(entry: UnifiedSuggestBrand): Category {
  return {
    slug: entry.slug,
    name: entry.name,
    description: `${entry.name} brand`,
    gradient: BRAND_GRADIENT,
    icon: "🏷",
    searchSuggestionType: "brand",
  };
}

function mapStoreSuggestion(entry: UnifiedSuggestStore): Category {
  return {
    slug: entry.slug,
    name: entry.name,
    description: `${entry.name} store`,
    gradient: STORE_GRADIENT,
    icon: "🏪",
    searchSuggestionType: "store",
  };
}

function mapCategorySuggestion(entry: UnifiedSuggestCategory): Category {
  return {
    slug: entry.slug,
    name: entry.name,
    description: entry.name,
    gradient: CATEGORY_GRADIENT,
    icon: "📦",
  };
}

function buildTerms(
  products: Product[],
  brands: Category[],
  stores: Category[],
  scope: SearchMarketplaceScope = "all",
): SearchTermSuggestion[] {
  const seen = new Set<string>();
  const terms: SearchTermSuggestion[] = [];

  const push = (label: string) => {
    const trimmed = label.trim();
    if (!trimmed) return;
    const key = trimmed.toLowerCase();
    if (seen.has(key)) return;
    seen.add(key);
    terms.push({
      type: "term",
      label: trimmed,
      href: buildUnifiedSearchHref(trimmed, scope),
    });
  };

  for (const product of products) {
    push(product.name);
    if (terms.length >= MAX_TERM_RESULTS) break;
  }
  for (const brand of brands) {
    if (terms.length >= MAX_TERM_RESULTS) break;
    push(brand.name);
  }
  for (const store of stores) {
    if (terms.length >= MAX_TERM_RESULTS) break;
    push(store.name);
  }

  return terms.slice(0, MAX_TERM_RESULTS);
}

/** Map unified suggest API payload into header SearchResults. */
export function mapUnifiedSuggestToSearchResults(
  data: UnifiedSearchSuggestData,
): SearchResults {
  const scope: SearchMarketplaceScope =
    data.scope === "china" || data.scope === "tz" ? data.scope : "all";
  const products = (data.products ?? [])
    .slice(0, MAX_PRODUCT_RESULTS)
    .map(mapSuggestProduct);

  const brands = (data.brands ?? [])
    .slice(0, MAX_CATEGORY_RESULTS)
    .map(mapBrandSuggestion);

  const stores = (data.stores ?? [])
    .slice(0, MAX_CATEGORY_RESULTS)
    .map(mapStoreSuggestion);

  const categories = (data.categories ?? [])
    .slice(0, MAX_CATEGORY_RESULTS)
    .map(mapCategorySuggestion);

  return {
    products,
    groups: [],
    categories,
    brands,
    stores,
    terms: buildTerms(products, brands, stores, scope),
  };
}

/** Href helpers for tests / consumers — keep in sync with SearchCategoryRow. */
export function resolveSuggestionHref(
  entry: Category,
  origin?: ProductOrigin,
): string {
  if (entry.searchSuggestionType === "brand") {
    return buildSearchBrandHref(entry.slug, origin);
  }
  if (entry.searchSuggestionType === "store") {
    return buildSearchStoreHref(entry.slug);
  }
  return buildSearchCategoryHref(entry.slug, origin);
}

export function resolveProductSuggestionHref(product: Product): string {
  return `/products/${product.slug}`;
}
