import type { Category, Product, ProductOrigin } from "@/lib/types/catalog";
import { resolveProductType } from "@/lib/catalog/product-type";
import {
  MAX_CATEGORY_RESULTS,
  MAX_PRODUCT_RESULTS,
  MAX_TERM_RESULTS,
  SEARCH_CACHE_MAX_ENTRIES,
} from "@/lib/search/constants";
import {
  allTokensMatchFields,
  expandTermVariants,
  normalizeSearchInput,
  normalizeSearchableText,
  termMatchesInText,
  textMatchesAnyVariant,
  tokenizeSearchQuery,
} from "@/lib/search/normalize";
import { resolveProductSearchLabels } from "@/lib/search/product-search-labels";
import { buildProductSearchHref } from "@/lib/search/search-url";
import type {
  SearchMatchTier,
  SearchProductGroup,
  SearchResults,
  SearchTermSuggestion,
} from "@/lib/search/types";
import { SEARCH_TIER_LABELS, SEARCH_TIER_ORDER } from "@/lib/search/types";

export type SmartSearchOptions = {
  origin?: ProductOrigin;
  limit?: number;
  activeOnly?: boolean;
  /** Live API categories — never seed data. */
  liveCategories?: Category[];
  /** Live API brands mapped as Category-shaped rows. */
  liveBrands?: Category[];
};

type ProductMatchResult = {
  product: Product;
  tier: SearchMatchTier;
  score: number;
};

const RELEVANCE = {
  EXACT_NAME: 1000,
  NAME_STARTS: 800,
  NAME_PARTIAL: 600,
  CATEGORY: 200,
  SUBCATEGORY: 100,
  FUZZY: 40,
} as const;

const EMPTY_RESULTS: SearchResults = {
  products: [],
  groups: [],
  categories: [],
  terms: [],
};

const searchCache = new Map<string, SearchResults>();

function buildCacheKey(
  query: string,
  origin?: ProductOrigin,
  productIds?: string,
): string {
  return `${origin ?? "all"}::${query}::${productIds ?? ""}`;
}

export function clearSearchQueryCache(): void {
  searchCache.clear();
}

/** Strict origin filter — no cross-contamination between China and Buy from Dar. */
function applyOriginFilter(products: Product[], origin?: ProductOrigin): Product[] {
  if (!origin) {
    return products;
  }

  return products.filter((product) => product.origin === origin);
}

function isExactNameMatch(name: string, query: string): boolean {
  const phraseVariants = expandTermVariants(query);
  return phraseVariants.some((variant) => name === variant);
}

function isNameStartsMatch(name: string, query: string): boolean {
  const phraseVariants = expandTermVariants(query);
  return phraseVariants.some((variant) => variant.length > 0 && name.startsWith(variant));
}

function isNamePartialMatch(name: string, tokens: string[]): boolean {
  return tokens.every((token) => termMatchesInText(name, token));
}

function matchStrength(text: string, term: string): number {
  const variants = expandTermVariants(term);
  if (textMatchesAnyVariant(text, variants)) {
    return RELEVANCE.NAME_PARTIAL;
  }
  if (termMatchesInText(text, term)) {
    return RELEVANCE.FUZZY;
  }
  return 0;
}

function classifyProductMatch(product: Product, query: string): ProductMatchResult | null {
  const normalizedQuery = normalizeSearchInput(query);
  if (!normalizedQuery) {
    return null;
  }

  const labels = resolveProductSearchLabels(product);
  const name = normalizeSearchableText(product.name);
  const category = normalizeSearchableText(
    labels.categoryLabel || product.brand || product.categorySlug || "",
  );
  const subcategory = normalizeSearchableText(labels.subcategoryLabel);
  const tokens = tokenizeSearchQuery(normalizedQuery);
  const fields = [name, category, subcategory];

  if (!allTokensMatchFields(tokens, fields)) {
    return null;
  }

  let tier: SearchMatchTier = "partial";
  let score = 0;

  if (isExactNameMatch(name, normalizedQuery)) {
    tier = "exact-name";
    score += RELEVANCE.EXACT_NAME;
  } else if (isNameStartsMatch(name, normalizedQuery)) {
    tier = "exact-name";
    score += RELEVANCE.NAME_STARTS;
  } else if (isNamePartialMatch(name, tokens)) {
    tier = "partial";
    score += RELEVANCE.NAME_PARTIAL;
  } else if (tokens.some((token) => termMatchesInText(category, token))) {
    tier = "category";
    score += RELEVANCE.CATEGORY;
  } else if (tokens.some((token) => termMatchesInText(subcategory, token))) {
    tier = "subcategory";
    score += RELEVANCE.SUBCATEGORY;
  } else {
    tier = "partial";
    score += RELEVANCE.FUZZY;
  }

  for (const token of tokens) {
    score += matchStrength(name, token);
    score += matchStrength(category, token) * 0.5;
    score += matchStrength(subcategory, token) * 0.25;
  }

  return { product, tier, score };
}

function sortMatches(a: ProductMatchResult, b: ProductMatchResult): number {
  const tierDiff = SEARCH_TIER_ORDER[a.tier] - SEARCH_TIER_ORDER[b.tier];
  if (tierDiff !== 0) {
    return tierDiff;
  }
  if (b.score !== a.score) {
    return b.score - a.score;
  }
  return b.product.rating - a.product.rating;
}

function buildProductGroups(matches: ProductMatchResult[]): SearchProductGroup[] {
  const grouped = new Map<SearchMatchTier, Product[]>();

  for (const match of matches) {
    const existing = grouped.get(match.tier) ?? [];
    existing.push(match.product);
    grouped.set(match.tier, existing);
  }

  return (Object.keys(SEARCH_TIER_ORDER) as SearchMatchTier[])
    .filter((tier) => (grouped.get(tier)?.length ?? 0) > 0)
    .map((tier) => ({
      tier,
      label: SEARCH_TIER_LABELS[tier],
      products: grouped.get(tier) ?? [],
    }));
}

function categoryMatchesQuery(categoryName: string, categorySlug: string, query: string): boolean {
  const tokens = tokenizeSearchQuery(query);
  if (tokens.length === 0) {
    return false;
  }

  const name = normalizeSearchableText(categoryName);
  const slug = normalizeSearchableText(categorySlug);

  return allTokensMatchFields(tokens, [name, slug]);
}

/** Build autocomplete terms from live product / category / brand labels only. */
function buildLiveTermSuggestions(
  query: string,
  products: Product[],
  categories: Category[],
  brands: Category[],
  origin?: ProductOrigin,
): SearchTermSuggestion[] {
  const normalizedQuery = normalizeSearchInput(query);
  if (!normalizedQuery) {
    return [];
  }

  const seen = new Set<string>();
  const terms: SearchTermSuggestion[] = [];

  const pushLabel = (label: string) => {
    const trimmed = label.trim();
    if (!trimmed) return;
    const key = normalizeSearchableText(trimmed);
    if (!key || seen.has(key)) return;
    if (!categoryMatchesQuery(trimmed, key, normalizedQuery)) return;
    seen.add(key);
    terms.push({
      type: "term",
      label: trimmed,
      href: buildProductSearchHref(trimmed, origin),
    });
  };

  for (const product of products) {
    pushLabel(product.name);
    if (product.brand) pushLabel(product.brand);
    if (terms.length >= MAX_TERM_RESULTS) break;
  }

  for (const category of [...categories, ...brands]) {
    if (terms.length >= MAX_TERM_RESULTS) break;
    pushLabel(category.name);
  }

  return terms.slice(0, MAX_TERM_RESULTS);
}

function computeSearchResults(
  catalog: Product[],
  query: string,
  options: SmartSearchOptions = {},
): SearchResults {
  const normalizedQuery = normalizeSearchInput(query);
  const { origin, liveCategories = [], liveBrands = [] } = options;

  if (!normalizedQuery) {
    return EMPTY_RESULTS;
  }

  let pool = catalog.filter((product) =>
    options.activeOnly === false ? true : product.status === "active",
  );
  pool = applyOriginFilter(pool, origin);

  const matches = pool
    .map((product) => classifyProductMatch(product, normalizedQuery))
    .filter((entry): entry is ProductMatchResult => entry !== null)
    .sort(sortMatches);

  const limited = matches.slice(0, options.limit ?? MAX_PRODUCT_RESULTS);
  const products = limited.map((entry) => entry.product);
  const groups = buildProductGroups(limited);

  const matchedCategories =
    origin === "tz"
      ? []
      : liveCategories
          .filter((category) =>
            categoryMatchesQuery(category.name, category.slug, normalizedQuery),
          )
          .slice(0, MAX_CATEGORY_RESULTS);

  const matchedBrands =
    origin === "china"
      ? []
      : liveBrands
          .filter((brand) => categoryMatchesQuery(brand.name, brand.slug, normalizedQuery))
          .slice(0, MAX_CATEGORY_RESULTS);

  const terms = buildLiveTermSuggestions(
    normalizedQuery,
    products,
    liveCategories,
    liveBrands,
    origin,
  );

  return {
    products,
    groups,
    categories: [...matchedCategories, ...matchedBrands].slice(0, MAX_CATEGORY_RESULTS),
    terms,
  };
}

/** Ranked product search against a provided live product list (no seed injection). */
export function smartSearchProducts(
  catalog: Product[],
  query: string,
  options: SmartSearchOptions = {},
): Product[] {
  const normalizedQuery = normalizeSearchInput(query);
  const { origin, limit, activeOnly = true } = options;

  if (!normalizedQuery) {
    let pool = catalog;
    if (activeOnly) {
      pool = pool.filter((product) => product.status === "active");
    }
    pool = applyOriginFilter(pool, origin);
    return limit ? pool.slice(0, limit) : [...pool];
  }

  return searchCatalog(catalog, normalizedQuery, {
    ...options,
    limit: limit ?? catalog.length,
  }).products;
}

/**
 * Rank / group products already returned from the live catalog API.
 * Does not invent products, popular terms, or seed categories.
 */
export function searchCatalog(
  catalog: Product[],
  query: string,
  options: SmartSearchOptions = {},
): SearchResults {
  const normalizedQuery = normalizeSearchInput(query);
  const { origin } = options;

  if (!normalizedQuery) {
    return EMPTY_RESULTS;
  }

  const productIds = catalog
    .map((product) => product.id)
    .sort()
    .join(",");
  const cacheKey = buildCacheKey(normalizedQuery, origin, productIds);
  const cached = searchCache.get(cacheKey);
  if (cached) {
    return cached;
  }

  const result = computeSearchResults(catalog, normalizedQuery, options);

  if (searchCache.size >= SEARCH_CACHE_MAX_ENTRIES) {
    const oldestKey = searchCache.keys().next().value;
    if (oldestKey) {
      searchCache.delete(oldestKey);
    }
  }
  searchCache.set(cacheKey, result);

  return result;
}

/** Verify a product belongs to the requested marketplace scope. */
export function productMatchesOriginFilter(product: Product, origin?: ProductOrigin): boolean {
  if (!origin) {
    return true;
  }
  return (
    product.origin === origin &&
    resolveProductType(product) === (origin === "tz" ? "local" : "china")
  );
}
