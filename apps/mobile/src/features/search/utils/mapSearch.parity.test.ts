import {
  isSearchMappingFailure,
  mapSearchHit,
  mapSearchProductsResponse,
} from './mapSearch';

/** Production-shaped configurable card: Laravel MissingValue leaked as {}. */
const collaredShirtDressHit = {
  is_purchasable: true,
  availability_status: 'available',
  id: '019fe71b-6fac-73cf-9c9e-6919f2352bd8',
  slug: 'collared-shirt-dress',
  name: 'COLLARED SHIRT DRESS',
  price: '23000.00',
  compare_at_price: null,
  primary_image: {
    id: '019fe71c-1607-72e8-80a1-073383269b4c',
    url: 'https://api.chinaordertz.com/storage/products/example.png',
    path: 'products/example.png',
    alt_text: null,
  },
  marketplace: 'china',
  commerce_channel_code: 'CHINA_IMPORT',
  brand: { id: 'b1', slug: 'zion-mode', name: 'Zion Mode' },
  store: null,
  // Root cause of false empty results — empty object, not boolean.
  stock: {},
  in_stock: {},
  inventory: {},
  relevance_score: 500,
  matched_on: ['name'],
};

const upsHit = {
  id: 'ups-1',
  slug: 'dc-mini-ups',
  name: 'DC MINI UPS',
  price: '45000.00',
  marketplace: 'china',
  commerce_channel_code: 'CHINA_IMPORT',
  in_stock: true,
  stock: 20,
  is_purchasable: true,
  availability_status: 'available',
  relevance_score: 500,
  matched_on: ['name'],
};

describe('mapSearchHit — production stock field shapes', () => {
  it('maps COLLARED SHIRT DRESS even when in_stock is an empty object', () => {
    const hit = mapSearchHit(collaredShirtDressHit);
    expect(hit).not.toBeNull();
    expect(hit?.name).toBe('COLLARED SHIRT DRESS');
    expect(hit?.slug).toBe('collared-shirt-dress');
    expect(hit?.inStock).toBeNull();
    expect(hit?.isPurchasable).toBe(true);
  });

  it('keeps boolean in_stock for simple products like UPS', () => {
    const hit = mapSearchHit(upsHit);
    expect(hit?.name).toBe('DC MINI UPS');
    expect(hit?.inStock).toBe(true);
  });
});

describe('mapSearchProductsResponse — Dress / Kimoni / UPS parity', () => {
  it('Dress envelope keeps COLLARED SHIRT DRESS in mapped hits', () => {
    const response = mapSearchProductsResponse({
      data: [collaredShirtDressHit, upsHit],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 24,
        total: 2,
        q: 'Dress',
        scope: 'all',
      },
    });

    expect(response.hits.map((hit) => hit.name)).toEqual([
      'COLLARED SHIRT DRESS',
      'DC MINI UPS',
    ]);
    expect(response.total).toBe(2);
    expect(isSearchMappingFailure(response)).toBe(false);
  });

  it('case-insensitive dress query mapping is identical', () => {
    const lower = mapSearchProductsResponse({
      data: [collaredShirtDressHit],
      meta: { current_page: 1, last_page: 1, per_page: 24, total: 1, q: 'dress', scope: 'all' },
    });
    expect(lower.hits[0]?.name).toBe('COLLARED SHIRT DRESS');
  });

  it('Kimoni zero-result envelope stays empty (no fake catalog fill)', () => {
    const response = mapSearchProductsResponse({
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 24,
        total: 0,
        q: 'Kimoni',
        scope: 'all',
      },
    });
    expect(response.hits).toEqual([]);
    expect(response.total).toBe(0);
    expect(isSearchMappingFailure(response)).toBe(false);
  });

  it('detects mapping failure when total>0 but every row fails parse', () => {
    const response = mapSearchProductsResponse({
      data: [{ id: 'x' }], // missing name → drop
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 24,
        total: 6,
        q: 'Dress',
        scope: 'all',
      },
    });
    expect(response.hits).toEqual([]);
    expect(isSearchMappingFailure(response)).toBe(true);
  });
});
