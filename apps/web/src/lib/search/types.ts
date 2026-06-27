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

export type SearchResults = {
  products: Product[];
  categories: Category[];
  terms: SearchTermSuggestion[];
};
