export { fetchSearchSuggestions, fetchSearchProducts } from './api/searchApi';
export { useSearchSuggestions } from './hooks/useSearchSuggestions';
export {
  useSearchProducts,
  searchProductsQueryKey,
  buildSearchProductsRequestParams,
} from './hooks/useSearchProducts';
export { SearchScreen } from './screens/SearchScreen';
export { SearchInput } from './components/SearchInput';
export { SuggestionList } from './components/SuggestionList';
export { SearchResultCard } from './components/SearchResultCard';
export { resolveHitJourney } from './utils/resolveHitJourney';
export {
  journeyToSearchScope,
  journeySearchLabel,
  shouldFetchSearch,
  resolveSearchScope,
  DEFAULT_SEARCH_SCOPE,
  nameMatchesSearchQuery,
  searchScopeLabel,
} from './utils/journeyScope';
export type { SearchScope } from './utils/journeyScope';
export {
  mapSearchHit,
  mapSearchSuggestions,
  mapSearchProductsResponse,
  isSearchMappingFailure,
} from './utils/mapSearch';
export { getSearchErrorMessage } from './utils/searchErrorMessage';
export type {
  SearchHit,
  SearchResponse,
  SearchSuggestion,
  SearchSuggestionsResult,
  SearchSort,
} from './models/types';
