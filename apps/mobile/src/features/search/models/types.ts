import type { SearchScope } from '../utils/journeyScope';

export type SearchSuggestionKind = 'product' | 'brand' | 'store' | 'category';

export type SearchSuggestion = {
  kind: SearchSuggestionKind;
  id: string;
  label: string;
  /** Query text to run when the suggestion is selected. */
  query: string;
  slug?: string | null;
};

export type SearchHit = {
  id: string;
  slug: string;
  name: string;
  price: string | number | null;
  imageUrl: string | null;
  /** Server marketplace; null when missing/unknown — never invent China. */
  marketplace: string | null;
  commerceChannelCode?: string | null;
  storeSlug?: string | null;
  storeName?: string | null;
  brandName?: string | null;
  relevanceScore?: number | null;
  availabilityStatus?: string | null;
  matchedOn?: string[];
};

export type SearchSuggestionsResult = {
  q: string;
  scope: SearchScope | string;
  suggestions: SearchSuggestion[];
};

export type SearchResponse = {
  hits: SearchHit[];
  page: number;
  lastPage: number;
  perPage: number;
  total: number;
  q: string;
  scope: SearchScope | string;
};

export type SearchSort = 'relevance' | 'newest';
