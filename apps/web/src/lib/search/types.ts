import type { Category } from "@/lib/types/catalog";
import type { Product } from "@/lib/types/catalog";

export type SearchTermSuggestion = {
  type: "term";
  label: string;
  href: string;
};

export type SearchProductSuggestion = {
  type: "product";
  product: Product;
};

export type SearchCategorySuggestion = {
  type: "category";
  category: Category;
};

export type SearchSuggestion =
  | SearchTermSuggestion
  | SearchProductSuggestion
  | SearchCategorySuggestion;

export type SearchMatchTier = "exact-name" | "category" | "subcategory" | "partial";

export type SearchProductGroup = {
  tier: SearchMatchTier;
  label: string;
  products: Product[];
};

export type SearchResults = {
  products: Product[];
  groups: SearchProductGroup[];
  categories: Category[];
  terms: SearchTermSuggestion[];
};

export const SEARCH_TIER_LABELS: Record<SearchMatchTier, string> = {
  "exact-name": "Best matches",
  category: "Categories",
  subcategory: "Subcategories",
  partial: "Related products",
};

export const SEARCH_TIER_ORDER: Record<SearchMatchTier, number> = {
  "exact-name": 0,
  category: 1,
  subcategory: 2,
  partial: 3,
};
