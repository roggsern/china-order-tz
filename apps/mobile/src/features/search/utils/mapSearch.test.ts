import { mapSearchHit, mapSearchProductsResponse, mapSearchSuggestions } from './mapSearch';

describe('mapSearchSuggestions', () => {
  it('flattens product and entity suggestion buckets', () => {
    const result = mapSearchSuggestions({
      data: {
        q: 'zion',
        scope: 'china',
        products: [
          {
            id: 'p1',
            slug: 'gown',
            name: 'Evening Gown',
            price: '90000',
            marketplace: 'china',
            primary_image: { url: 'https://cdn.example/g.jpg' },
            brand: { id: 'b1', name: 'Zion Mode', slug: 'zion-mode' },
          },
        ],
        brands: [{ id: 2, slug: 'zion-mode', name: 'Zion Mode', kind: 'catalog_brand' }],
        stores: [],
        categories: [{ id: 'c1', slug: 'phones', name: 'Phones', kind: 'category' }],
      },
    });

    expect(result.q).toBe('zion');
    expect(result.scope).toBe('china');
    expect(result.suggestions).toEqual([
      expect.objectContaining({
        kind: 'product',
        // Backend may return brand-related products; mobile shows brand when present.
        label: 'Evening Gown · Zion Mode',
        query: 'Evening Gown',
        slug: 'gown',
      }),
      expect.objectContaining({
        kind: 'brand',
        label: 'Zion Mode',
        query: 'Zion Mode',
      }),
      expect.objectContaining({
        kind: 'category',
        label: 'Phones',
        query: 'Phones',
      }),
    ]);
  });

  it('returns empty suggestions for empty payload', () => {
    expect(mapSearchSuggestions({ data: { q: '', scope: 'tz', products: [] } })).toEqual({
      q: '',
      scope: 'tz',
      suggestions: [],
    });
  });
});

describe('mapSearchHit / mapSearchProductsResponse', () => {
  it('maps product hits with marketplace, store, sale, and availability fields', () => {
    const hit = mapSearchHit({
      id: 'p-tz',
      slug: 'dress',
      name: 'Local Dress',
      price: 45000,
      compare_at_price: 60000,
      marketplace: 'tz',
      commerce_channel_code: 'TZ_LOCAL',
      availability_status: 'available',
      is_purchasable: true,
      in_stock: true,
      primary_image: { url: 'https://cdn.example/d.jpg' },
      brand: { id: 'b1', name: 'Local Brand', slug: 'local-brand' },
      store: { id: 's1', name: 'ZION MODE', slug: 'zion-mode' },
      relevance_score: 12,
      matched_on: ['name', 'store'],
    });

    expect(hit).toMatchObject({
      id: 'p-tz',
      slug: 'dress',
      name: 'Local Dress',
      price: 45000,
      compareAtPrice: 60000,
      marketplace: 'tz',
      commerceChannelCode: 'TZ_LOCAL',
      storeSlug: 'zion-mode',
      storeName: 'ZION MODE',
      brandName: 'Local Brand',
      imageUrl: 'https://cdn.example/d.jpg',
      relevanceScore: 12,
      availabilityStatus: 'available',
      isPurchasable: true,
      inStock: true,
    });
  });

  it('preserves category/store suggestion slugs for Browse deep-links', () => {
    const result = mapSearchSuggestions({
      data: {
        q: 'zion',
        scope: 'tz',
        products: [],
        brands: [],
        stores: [{ id: 's1', slug: 'zion-mode', name: 'ZION MODE', kind: 'store' }],
        categories: [{ id: 'c1', slug: 'dresses', name: 'Dresses', kind: 'category' }],
      },
    });

    expect(result.suggestions).toEqual([
      expect.objectContaining({
        kind: 'store',
        slug: 'zion-mode',
        label: 'ZION MODE',
      }),
      expect.objectContaining({
        kind: 'category',
        slug: 'dresses',
        label: 'Dresses',
      }),
    ]);
  });

  it('maps paginated search products envelope', () => {
    const response = mapSearchProductsResponse({
      data: [
        {
          id: 'p1',
          slug: 'widget',
          name: 'Widget',
          price: '1000',
          marketplace: 'china',
          commerce_channel_code: 'CHINA_IMPORT',
        },
      ],
      meta: {
        current_page: 1,
        last_page: 3,
        per_page: 24,
        total: 50,
        q: 'widget',
        scope: 'china',
      },
    });

    expect(response.hits).toHaveLength(1);
    expect(response.hits[0]?.slug).toBe('widget');
    expect(response).toMatchObject({
      page: 1,
      lastPage: 3,
      perPage: 24,
      total: 50,
      q: 'widget',
      scope: 'china',
    });
  });

  it('returns null for incomplete hits', () => {
    expect(mapSearchHit({ name: 'Only name' })).toBeNull();
    expect(mapSearchHit({ id: 'p1' })).toBeNull();
  });

  it('maps china marketplace as china', () => {
    const hit = mapSearchHit({
      id: 'p1',
      slug: 'widget',
      name: 'Widget',
      marketplace: 'china',
    });
    expect(hit?.marketplace).toBe('china');
  });

  it('maps tz marketplace as tz', () => {
    const hit = mapSearchHit({
      id: 'p2',
      slug: 'dress',
      name: 'Dress',
      marketplace: 'tz',
    });
    expect(hit?.marketplace).toBe('tz');
  });

  it('missing marketplace does not become china', () => {
    const hit = mapSearchHit({
      id: 'p3',
      slug: 'mystery',
      name: 'Mystery',
    });
    expect(hit?.marketplace).toBeNull();
  });

  it('unknown marketplace stays as provided (not remapped to china)', () => {
    const hit = mapSearchHit({
      id: 'p4',
      slug: 'odd',
      name: 'Odd',
      marketplace: 'global',
    });
    expect(hit?.marketplace).toBe('global');
    expect(hit?.marketplace).not.toBe('china');
  });
});
