import { categories, getCategoryBySlug } from "@/lib/catalog/categories";
import { buyFromTzBrandMenu, formatBrandDisplayName } from "@/lib/catalog/brands";
import { resolveProductType } from "@/lib/catalog/product-type";
import type { Product } from "@/lib/types/catalog";
import type { ProductOrigin } from "@/lib/types/catalog";
import {
  MAX_CATEGORY_RESULTS,
  MAX_PRODUCT_RESULTS,
  MAX_TERM_RESULTS,
  POPULAR_SEARCHES,
  SEARCH_CACHE_MAX_ENTRIES,
  TRENDING_SEARCHES,
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

const searchCache = new Map<string, SearchResults>();

function buildCacheKey(query: string, origin?: ProductOrigin): string {
  return `${origin ?? "all"}::${query}`;
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
  const category = normalizeSearchableText(labels.categoryLabel);
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

/** Ranked product search — exact name, category, subcategory, then partial/fuzzy. */
export function smartSearchProducts(
  catalog: Product[],
  query: string,
  options: SmartSearchOptions = {},
): Product[] {
  const normalizedQuery = normalizeSearchInput(query);
  const { origin, limit, activeOnly = true } = options;

  if (!normalizedQuery) {
    return limit ? catalog.slice(0, limit) : [...catalog];
  }

  let pool = catalog;
  if (activeOnly) {
    pool = pool.filter((product) => product.status === "active");
  }
  pool = applyOriginFilter(pool, origin);

  const ranked = pool
    .map((product) => classifyProductMatch(product, normalizedQuery))
    .filter((entry): entry is ProductMatchResult => entry !== null)
    .sort(sortMatches)
    .map((entry) => entry.product);

  return limit ? ranked.slice(0, limit) : ranked;
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

function matchSearchTerms(
  query: string,
  pool: readonly string[],
  origin?: ProductOrigin,
): SearchTermSuggestion[] {
  const normalizedQuery = normalizeSearchInput(query);

  const terms = !normalizedQuery
    ? pool.slice(0, MAX_TERM_RESULTS)
    : pool.filter((term) => {
        const tokens = tokenizeSearchQuery(normalizedQuery);
        const label = normalizeSearchableText(term);
        return allTokensMatchFields(tokens, [label]);
      });

  return terms.slice(0, MAX_TERM_RESULTS).map((label) => ({
    type: "term" as const,
    label,
    href: buildProductSearchHref(label, origin),
  }));
}

function computeSearchResults(
  catalog: Product[],
  query: string,
  origin?: ProductOrigin,
): SearchResults {
  const normalizedQuery = normalizeSearchInput(query);

  if (!normalizedQuery) {
    return {
      products: [],
      groups: [],
      categories: [],
      terms: [
        ...matchSearchTerms("", TRENDING_SEARCHES, origin),
        ...matchSearchTerms("", POPULAR_SEARCHES, origin).filter(
          (term) => !TRENDING_SEARCHES.some((entry) => entry === term.label),
        ),
      ].slice(0, MAX_TERM_RESULTS * 2),
    };
  }

  let pool = catalog.filter((product) => product.status === "active");
  pool = applyOriginFilter(pool, origin);

  const matches = pool
    .map((product) => classifyProductMatch(product, normalizedQuery))
    .filter((entry): entry is ProductMatchResult => entry !== null)
    .sort(sortMatches);

  const products = matches.slice(0, MAX_PRODUCT_RESULTS).map((entry) => entry.product);
  const groups = buildProductGroups(matches.slice(0, MAX_PRODUCT_RESULTS));

  const matchedCategories =
    origin === "tz"
      ? []
      : categories
          .filter((category) => categoryMatchesQuery(category.name, category.slug, normalizedQuery))
          .slice(0, MAX_CATEGORY_RESULTS);

  const matchedLocalBrands =
    origin === "china"
      ? []
      : buyFromTzBrandMenu
          .filter((brand) =>
            categoryMatchesQuery(formatBrandDisplayName(brand.name), brand.slug, normalizedQuery),
          )
          .slice(0, MAX_CATEGORY_RESULTS)
          .map((brand) => {
            const existing = getCategoryBySlug(brand.slug);
            if (existing) {
              return existing;
            }

            return {
              slug: brand.slug,
              name: formatBrandDisplayName(brand.name),
              description: `${formatBrandDisplayName(brand.name)} — Buy from Dar`,
              gradient: "from-amber-400 via-orange-500 to-rose-500",
              icon: brand.icon,
            };
          });

  const terms = [
    ...matchSearchTerms(normalizedQuery, TRENDING_SEARCHES, origin),
    ...matchSearchTerms(normalizedQuery, POPULAR_SEARCHES, origin),
  ].filter(
    (term, index, array) => array.findIndex((entry) => entry.label === term.label) === index,
  );

  return {
    products,
    groups,
    categories: [...matchedCategories, ...matchedLocalBrands].slice(0, MAX_CATEGORY_RESULTS),
    terms: terms.slice(0, MAX_TERM_RESULTS),
  };
}

/** Client-side search against a cached product catalog — no per-keystroke API calls. */
export function searchCatalog(
  catalog: Product[],
  query: string,
  options: Pick<SmartSearchOptions, "origin"> = {},
): SearchResults {
  const normalizedQuery = normalizeSearchInput(query);
  const { origin } = options;

  if (!normalizedQuery) {
    return computeSearchResults(catalog, normalizedQuery, origin);
  }

  const cacheKey = buildCacheKey(normalizedQuery, origin);
  const cached = searchCache.get(cacheKey);
  if (cached) {
    return cached;
  }

  const result = computeSearchResults(catalog, normalizedQuery, origin);

  if (searchCache.size >= SEARCH_CACHE_MAX_ENTRIES) {
    const oldestKey = searchCache.keys().next().value;
    if (oldestKey) {
      searchCache.delete(oldestKey);
    }
  }
  searchCache.set(cacheKey, result);

  return result;
}

export function getCategoryForProduct(product: Product) {
  return getCategoryBySlug(product.categorySlug);
}

/** Verify a product belongs to the requested marketplace scope (for filter isolation tests). */
export function productMatchesOriginFilter(product: Product, origin?: ProductOrigin): boolean {
  if (!origin) {
    return true;
  }
  return product.origin === origin && resolveProductType(product) === (origin === "tz" ? "local" : "china");
}
