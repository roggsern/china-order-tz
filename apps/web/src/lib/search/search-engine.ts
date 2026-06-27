import { categories, getCategoryBySlug } from "@/lib/catalog/categories";
import type { Product } from "@/lib/types/catalog";
import {
  MAX_CATEGORY_RESULTS,
  MAX_PRODUCT_RESULTS,
  MAX_TERM_RESULTS,
  POPULAR_SEARCHES,
  TRENDING_SEARCHES,
} from "@/lib/search/constants";
import type { SearchResults, SearchTermSuggestion } from "@/lib/search/types";

function normalizeQuery(query: string): string {
  return query.trim().toLowerCase();
}

function productMatchesQuery(product: Product, query: string): boolean {
  const q = normalizeQuery(query);
  if (!q) {
    return false;
  }

  return (
    product.name.toLowerCase().includes(q) ||
    product.description.toLowerCase().includes(q) ||
    product.categorySlug.toLowerCase().includes(q) ||
    (product.brand?.toLowerCase().includes(q) ?? false) ||
    (product.badge?.toLowerCase().includes(q) ?? false)
  );
}

function categoryMatchesQuery(categoryName: string, categorySlug: string, query: string): boolean {
  const q = normalizeQuery(query);
  if (!q) {
    return false;
  }

  return categoryName.toLowerCase().includes(q) || categorySlug.toLowerCase().replace(/-/g, " ").includes(q);
}

function matchSearchTerms(query: string, pool: readonly string[]): SearchTermSuggestion[] {
  const q = normalizeQuery(query);
  if (!q) {
    return pool.slice(0, MAX_TERM_RESULTS).map((label) => ({
      type: "term" as const,
      label,
      href: `/products?q=${encodeURIComponent(label)}`,
    }));
  }

  return pool
    .filter((term) => term.toLowerCase().includes(q))
    .slice(0, MAX_TERM_RESULTS)
    .map((label) => ({
      type: "term" as const,
      label,
      href: `/products?q=${encodeURIComponent(label)}`,
    }));
}

/** Client-side search against a cached product catalog — no per-keystroke API calls. */
export function searchCatalog(catalog: Product[], query: string): SearchResults {
  const q = normalizeQuery(query);
  const activeProducts = catalog.filter((product) => product.status === "active");

  if (!q) {
    return {
      products: [],
      categories: [],
      terms: [
        ...matchSearchTerms("", TRENDING_SEARCHES),
        ...matchSearchTerms("", POPULAR_SEARCHES).filter(
          (term) => !TRENDING_SEARCHES.some((entry) => entry === term.label),
        ),
      ].slice(0, MAX_TERM_RESULTS * 2),
    };
  }

  const products = activeProducts
    .filter((product) => productMatchesQuery(product, q))
    .sort((a, b) => {
      const aName = a.name.toLowerCase().startsWith(q) ? 0 : 1;
      const bName = b.name.toLowerCase().startsWith(q) ? 0 : 1;
      if (aName !== bName) return aName - bName;
      return b.rating - a.rating;
    })
    .slice(0, MAX_PRODUCT_RESULTS);

  const matchedCategories = categories
    .filter((category) => categoryMatchesQuery(category.name, category.slug, q))
    .slice(0, MAX_CATEGORY_RESULTS);

  const terms = [
    ...matchSearchTerms(q, TRENDING_SEARCHES),
    ...matchSearchTerms(q, POPULAR_SEARCHES),
  ].filter(
    (term, index, array) => array.findIndex((entry) => entry.label === term.label) === index,
  );

  return {
    products,
    categories: matchedCategories,
    terms: terms.slice(0, MAX_TERM_RESULTS),
  };
}

export function getCategoryForProduct(product: Product) {
  return getCategoryBySlug(product.categorySlug);
}
