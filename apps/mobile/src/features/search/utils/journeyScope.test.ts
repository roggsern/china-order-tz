import {
  DEFAULT_SEARCH_SCOPE,
  journeyToSearchScope,
  nameMatchesSearchQuery,
  resolveSearchScope,
  shouldFetchSearch,
} from './journeyScope';

describe('resolveSearchScope', () => {
  it('defaults to all (web storefront parity)', () => {
    expect(DEFAULT_SEARCH_SCOPE).toBe('all');
    expect(resolveSearchScope()).toBe('all');
    expect(resolveSearchScope(null)).toBe('all');
  });

  it('honors explicit china/tz/all chips', () => {
    expect(resolveSearchScope('china')).toBe('china');
    expect(resolveSearchScope('tz')).toBe('tz');
    expect(resolveSearchScope('all')).toBe('all');
  });
});

describe('journeyToSearchScope', () => {
  it('maps journeys for optional scoped filters only', () => {
    expect(journeyToSearchScope('CHINA_IMPORT')).toBe('china');
    expect(journeyToSearchScope('TZ_LOCAL')).toBe('tz');
  });
});

describe('nameMatchesSearchQuery (server substring semantics)', () => {
  it('matches partial name DRESS → COLLARED SHIRT DRESS', () => {
    expect(nameMatchesSearchQuery('COLLARED SHIRT DRESS', 'DRESS')).toBe(true);
    expect(nameMatchesSearchQuery('COLLARED SHIRT DRESS', 'dress')).toBe(true);
    expect(nameMatchesSearchQuery('COLLARED SHIRT DRESS', ' shirt ')).toBe(true);
  });

  it('does not invent matches outside the name', () => {
    expect(nameMatchesSearchQuery('COLLARED SHIRT DRESS', 'jacket')).toBe(false);
    expect(nameMatchesSearchQuery('COLLARED SHIRT DRESS', '')).toBe(false);
  });
});

describe('shouldFetchSearch', () => {
  it('skips empty and whitespace-only queries', () => {
    expect(shouldFetchSearch('')).toBe(false);
    expect(shouldFetchSearch('   ')).toBe(false);
    expect(shouldFetchSearch('DRESS')).toBe(true);
  });
});
