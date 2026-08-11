import {
  journeySearchLabel,
  journeyToSearchScope,
  shouldFetchSearch,
} from './journeyScope';

describe('journeyToSearchScope', () => {
  it('maps CHINA_IMPORT → china and TZ_LOCAL → tz', () => {
    expect(journeyToSearchScope('CHINA_IMPORT')).toBe('china');
    expect(journeyToSearchScope('TZ_LOCAL')).toBe('tz');
  });

  it('does not expose technical scope values in journey labels', () => {
    expect(journeySearchLabel('CHINA_IMPORT')).toBe('Order from China');
    expect(journeySearchLabel('TZ_LOCAL')).toBe('Buy from TZ');
    expect(journeySearchLabel('CHINA_IMPORT')).not.toBe('china');
    expect(journeySearchLabel('TZ_LOCAL')).not.toBe('tz');
    expect(journeySearchLabel('CHINA_IMPORT')).not.toMatch(/scope=/i);
    expect(journeySearchLabel('TZ_LOCAL')).not.toMatch(/scope=/i);
  });
});

describe('shouldFetchSearch', () => {
  it('skips empty and whitespace-only queries', () => {
    expect(shouldFetchSearch('')).toBe(false);
    expect(shouldFetchSearch('   ')).toBe(false);
    expect(shouldFetchSearch('phone')).toBe(true);
  });
});
