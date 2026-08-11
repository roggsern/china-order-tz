import type { CommerceJourney } from '@/src/shared/types/commerce';

/** API search scope values — never show these in UI. */
export type SearchScope = 'china' | 'tz' | 'all';

/**
 * Map active commerce journey → Contract v1 search scope.
 * CHINA_IMPORT → china, TZ_LOCAL → tz.
 */
export function journeyToSearchScope(journey: CommerceJourney): SearchScope {
  return journey === 'TZ_LOCAL' ? 'tz' : 'china';
}

/** Friendly journey label for search chrome (no technical scope). */
export function journeySearchLabel(journey: CommerceJourney): string {
  return journey === 'TZ_LOCAL' ? 'Buy from TZ' : 'Order from China';
}

export function shouldFetchSearch(q: string): boolean {
  return q.trim().length > 0;
}
