import {
  mapProductCard,
  mapProductConfiguration,
  mapProductDetail,
  mapProductListResponse,
  pruneConfigurationSelections,
  buildConfigurationQuery,
} from './mapProduct';

describe('mapProductCard', () => {
  it('maps Contract v1 product card fields without inventing prices', () => {
    const product = mapProductCard({
      id: 'p1',
      slug: 'widget',
      name: 'Widget',
      price: '15000',
      compare_at_price: '18000',
      is_purchasable: true,
      availability_status: 'available',
      in_stock: true,
      primary_image: { id: 'img1', url: 'https://cdn.example/w.jpg', alt_text: null },
      commerce_channel_code: 'CHINA_IMPORT',
      commerce_source_label: 'Imported From China',
    });

    expect(product).toMatchObject({
      id: 'p1',
      slug: 'widget',
      name: 'Widget',
      price: '15000',
      compareAtPrice: '18000',
      imageUrl: 'https://cdn.example/w.jpg',
      isPurchasable: true,
      availabilityStatus: 'available',
      inStock: true,
      commerceChannelCode: 'CHINA_IMPORT',
    });
  });

  it('returns null when required identity fields are missing', () => {
    expect(mapProductCard({ name: 'Only name' })).toBeNull();
    expect(mapProductCard({ id: 'p1' })).toBeNull();
  });
});

describe('mapProductDetail', () => {
  it('maps images, variants, and API availability flags', () => {
    const detail = mapProductDetail({
      id: 'p1',
      slug: 'widget',
      name: 'Widget',
      price: 100,
      description: 'A product',
      availability_status: 'available',
      is_purchasable: true,
      images: [{ id: 'i1', url: 'https://cdn.example/1.jpg', alt_text: 'One' }],
      variants: [
        {
          id: 'v1',
          sku: 'W-1',
          name: 'Red',
          price: 110,
          in_stock: true,
          display_attributes: [{ attribute: 'Color', value: 'Red' }],
          primary_image: { id: 'vp', url: 'https://cdn.example/v-primary.jpg' },
          images: [{ id: 'vi', url: 'https://cdn.example/v1.jpg', alt_text: 'Var' }],
        },
      ],
      shipping_prices: { air: 20, sea: 10 },
    });

    expect(detail?.images).toHaveLength(1);
    expect(detail?.videos).toEqual([]);
    expect(detail?.variants[0]).toMatchObject({
      id: 'v1',
      price: 110,
      inStock: true,
      primaryImageUrl: 'https://cdn.example/v-primary.jpg',
    });
    expect(detail?.variants[0]?.images).toEqual([
      { id: 'vi', url: 'https://cdn.example/v1.jpg', altText: 'Var' },
    ]);
    expect(detail?.shippingPrices).toEqual({ air: 20, sea: 10 });
  });

  it('maps supported product videos and drops unsupported urls', () => {
    const detail = mapProductDetail({
      id: 'p2',
      slug: 'with-video',
      name: 'With video',
      price: 100,
      images: [],
      videos: [
        {
          id: 'vid-1',
          url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
          thumbnail_url: 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
          title: 'Walkthrough',
          alt_text: 'See it',
          sort_order: 2,
        },
        {
          id: 'vid-bad',
          url: 'https://example.com/file.mp4',
          sort_order: 1,
        },
        {
          id: 'vid-0',
          url: 'https://vimeo.com/123456789',
          title: 'Vimeo clip',
          sort_order: 0,
        },
      ],
    });

    expect(detail?.videos.map((video) => video.id)).toEqual(['vid-0', 'vid-1']);
    expect(detail?.videos[1]).toMatchObject({
      id: 'vid-1',
      title: 'Walkthrough',
      thumbnailUrl: 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
    });
  });
});

describe('mapProductConfiguration', () => {
  it('maps configuration attributes, allowed values, and match flags', () => {
    const config = mapProductConfiguration({
      product_id: 'p1',
      has_configurations: true,
      is_complete: true,
      is_in_stock: true,
      matched_configuration_id: 'cfg-1',
      availability_status: 'available',
      is_purchasable: true,
      capabilities: { has_configurations: true },
      allowed_value_ids: {
        'attr-color': ['val-black', 'val-white'],
        'attr-storage': ['val-128'],
      },
      configurations: [
        {
          id: 'cfg-1',
          price: '12000',
          attribute_value_ids: ['val-black', 'val-128'],
          in_stock: true,
        },
      ],
      attributes: [
        {
          id: 'attr-color',
          name: 'Color',
          slug: 'color',
          is_required: true,
          participates_in_configuration: true,
          values: [
            { id: 'val-black', value: 'Black', slug: 'black' },
            { id: 'val-white', value: 'White', slug: 'white' },
          ],
        },
        {
          id: 'attr-storage',
          name: 'Storage',
          slug: 'storage',
          is_required: true,
          participates_in_configuration: true,
          values: [
            { id: 'val-128', value: '128GB', slug: '128gb' },
            { id: 'val-256', value: '256GB', slug: '256gb' },
          ],
        },
      ],
    });

    expect(config).toMatchObject({
      productId: 'p1',
      hasConfigurations: true,
      isComplete: true,
      matchedConfigurationId: 'cfg-1',
      matchedUnitPrice: '12000',
      isPurchasable: true,
    });
    expect(config.configurations).toEqual([
      {
        id: 'cfg-1',
        attributeValueIds: ['val-black', 'val-128'],
        price: '12000',
        inStock: true,
        stock: null,
        name: null,
        sku: null,
      },
    ]);
    expect(config.attributes).toHaveLength(2);
    expect(config.attributes[0]?.values.map((value) => value.value)).toEqual([
      'Black',
      'White',
    ]);
    expect(config.allowedValueIds['attr-storage']).toEqual(['val-128']);
  });

  it('passes through configuration stock flags from API', () => {
    const config = mapProductConfiguration({
      product_id: 'p1',
      has_configurations: true,
      is_complete: false,
      is_in_stock: true,
      matched_configuration_id: null,
      attributes: [],
      capabilities: { has_configurations: true },
      availability_status: 'available',
      is_purchasable: true,
    });

    expect(config).toMatchObject({
      productId: 'p1',
      hasConfigurations: true,
      isInStock: true,
      isPurchasable: true,
    });
  });
});

describe('pruneConfigurationSelections / buildConfigurationQuery', () => {
  it('drops selections that the server no longer allows', () => {
    expect(
      pruneConfigurationSelections(
        { 'attr-color': 'val-red', 'attr-size': 'val-m' },
        { 'attr-color': ['val-black'], 'attr-size': ['val-m'] },
      ),
    ).toEqual({ 'attr-size': 'val-m' });
  });

  it('builds selections[attribute]=value query params for the API', () => {
    expect(
      buildConfigurationQuery({
        'attr-color': 'val-black',
      }),
    ).toEqual({
      'selections[attr-color]': 'val-black',
    });
  });
});

describe('mapProductListResponse', () => {
  it('maps paginated product lists', () => {
    const result = mapProductListResponse({
      data: [
        { id: 'p1', slug: 'a', name: 'A', price: 1 },
        { id: 'p2', slug: 'b', name: 'B', price: 2 },
      ],
      meta: { current_page: 1, last_page: 3, total: 40 },
    });

    expect(result.products).toHaveLength(2);
    expect(result.page).toBe(1);
    expect(result.lastPage).toBe(3);
    expect(result.total).toBe(40);
  });
});
