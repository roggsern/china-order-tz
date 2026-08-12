import type { CommerceJourney } from '@/src/shared/types/commerce';

/** API search scope values — never show these raw in customer chrome. */
export type SearchScope = 'china' | 'tz' | 'all';

/**
 * Web storefront defaults search to `all` so products are discoverable across
 * China import and TZ local corpora. Mobile previously locked scope to the
 * active journey, which caused false "No results" for products in the other
 * corpus (e.g. "DRESS" matching "COLLARED SHIRT DRESS" under scope=all).
 */
export const DEFAULT_SEARCH_SCOPE: SearchScope = 'all';

/**
 * Map active commerce journey → scoped search corpus.
 * Used when the customer explicitly filters China or TZ — not the default.
 */
export function journeyToSearchScope(journey: CommerceJourney): SearchScope {
  return journey === 'TZ_LOCAL' ? 'tz' : 'china';
}

/**
 * Resolve search API scope.
 * Explicit chip wins; otherwise web-parity default `all`.
 */
export function resolveSearchScope(explicit?: SearchScope | null): SearchScope {
  if (explicit === 'china' || explicit === 'tz' || explicit === 'all') {
    return explicit;
  }
  return DEFAULT_SEARCH_SCOPE;
}

/** Friendly label for search chrome (no technical scope tokens). */
export function journeySearchLabel(journey: CommerceJourney): string {
  return journey === 'TZ_LOCAL' ? 'Buy from TZ' : 'Order from China';
}

export function searchScopeLabel(scope: SearchScope): string {
  switch (scope) {
    case 'china':
      return 'China';
    case 'tz':
      return 'Tanzania';
    default:
      return 'All';
  }
}

export function shouldFetchSearch(q: string): boolean {
  return q.trim().length > 0;
}

/**
 * Documents server SearchRelevance semantics for regression tests:
 * case-insensitive substring match on product name (and other fields).
 * Does not invent client-side search — mirrors API normalize + LIKE.
 */
export function nameMatchesSearchQuery(productName: string, q: string): boolean {
  const needle = q.trim().toLowerCase();
  if (!needle) return false;
  return productName.toLowerCase().includes(needle);
}
