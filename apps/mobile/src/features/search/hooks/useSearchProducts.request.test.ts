import {
  buildSearchProductsRequestParams,
  searchProductsQueryKey,
} from './useSearchProducts';
import { resolveSearchScope } from '../utils/journeyScope';

describe('search request / queryKey scope wiring', () => {
  it('defaults scope to all like web', () => {
    expect(resolveSearchScope()).toBe('all');
    expect(buildSearchProductsRequestParams({ q: 'Dress' }).scope).toBe('all');
  });

  it('builds distinct request params for All / China / Tanzania', () => {
    expect(buildSearchProductsRequestParams({ q: 'Dress', scope: 'all' })).toEqual({
      q: 'Dress',
      scope: 'all',
      page: 1,
      per_page: 24,
      sort: 'relevance',
    });
    expect(buildSearchProductsRequestParams({ q: 'Dress', scope: 'china' }).scope).toBe(
      'china',
    );
    expect(buildSearchProductsRequestParams({ q: 'Dress', scope: 'tz' }).scope).toBe('tz');
  });

  it('queryKey changes when scope changes so React Query refetches', () => {
    const base = { q: 'Dress', sort: 'relevance' as const, perPage: 24 };
    const allKey = searchProductsQueryKey({ ...base, scope: 'all' });
    const chinaKey = searchProductsQueryKey({ ...base, scope: 'china' });
    const tzKey = searchProductsQueryKey({ ...base, scope: 'tz' });

    expect(allKey).not.toEqual(chinaKey);
    expect(chinaKey).not.toEqual(tzKey);
    expect(allKey[2]).toBe('all');
    expect(chinaKey[2]).toBe('china');
    expect(tzKey[2]).toBe('tz');
  });

  it('trims q and keeps UPS / Dress / Kimoni query text intact', () => {
    expect(buildSearchProductsRequestParams({ q: '  Dress  ' }).q).toBe('Dress');
    expect(buildSearchProductsRequestParams({ q: 'UPS' }).q).toBe('UPS');
    expect(buildSearchProductsRequestParams({ q: 'Kimoni' }).q).toBe('Kimoni');
  });
});
