import {
  flattenCatalogProductPages,
  getNextCatalogPageParam,
} from '../hooks/useCatalogQueries';
import type { CatalogProductCard, ProductListResult } from '../models/types';

function card(id: string): CatalogProductCard {
  return {
    id,
    slug: id,
    name: id,
    price: 1,
    imageUrl: null,
    isPurchasable: true,
    availabilityStatus: 'available',
    inStock: true,
  };
}

function page(
  pageNum: number,
  lastPage: number | null,
  ids: string[],
): ProductListResult {
  return {
    page: pageNum,
    lastPage,
    total: ids.length,
    products: ids.map(card),
  };
}

describe('catalog pagination helpers', () => {
  it('computes next page fetch param', () => {
    expect(getNextCatalogPageParam(page(1, 3, ['a']))).toBe(2);
    expect(getNextCatalogPageParam(page(3, 3, ['c']))).toBeUndefined();
    expect(getNextCatalogPageParam(page(1, null, ['a']))).toBeUndefined();
  });

  it('merges pagination pages without inventing products', () => {
    const merged = flattenCatalogProductPages([
      page(1, 2, ['a', 'b']),
      page(2, 2, ['b', 'c']),
    ]);
    expect(merged.map((p) => p.id)).toEqual(['a', 'b', 'c']);
  });

  it('refresh resets pages conceptually (first page only)', () => {
    const afterRefresh = flattenCatalogProductPages([page(1, 2, ['x', 'y'])]);
    expect(afterRefresh.map((p) => p.id)).toEqual(['x', 'y']);
  });

  it('handles empty pages on next-page error recovery', () => {
    expect(flattenCatalogProductPages(undefined)).toEqual([]);
    expect(flattenCatalogProductPages([])).toEqual([]);
    // Surviving first page after a failed next-page fetch
    expect(flattenCatalogProductPages([page(1, 2, ['a'])]).map((p) => p.id)).toEqual([
      'a',
    ]);
  });
});
