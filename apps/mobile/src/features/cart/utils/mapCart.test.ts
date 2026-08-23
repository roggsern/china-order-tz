import {
  buildAddToCartPayload,
  buildUpdateCartItemPayload,
  mapCart,
  mapCartItem,
  mapCartSummary,
} from './mapCart';

describe('buildAddToCartPayload', () => {
  it('builds a simple product payload with product_id + quantity', () => {
    expect(
      buildAddToCartPayload({
        productId: 'prod-1',
        quantity: 2,
      }),
    ).toEqual({
      product_id: 'prod-1',
      quantity: 2,
    });
  });

  it('builds a variant payload with product_variant_id + quantity', () => {
    expect(
      buildAddToCartPayload({
        productId: 'prod-1',
        productVariantId: 'variant-9',
        quantity: 1,
      }),
    ).toEqual({
      product_id: 'prod-1',
      product_variant_id: 'variant-9',
      quantity: 1,
    });
  });

  it('ignores blank variant ids for the simple path', () => {
    expect(
      buildAddToCartPayload({
        productId: 'prod-1',
        productVariantId: '  ',
        quantity: 3,
      }),
    ).toEqual({
      product_id: 'prod-1',
      quantity: 3,
    });
  });

  it('clamps invalid quantity to at least 1', () => {
    expect(
      buildAddToCartPayload({
        productId: 'prod-1',
        quantity: 0,
      }).quantity,
    ).toBe(1);
  });
});

describe('buildUpdateCartItemPayload', () => {
  it('builds quantity-only update payload from the server contract', () => {
    expect(buildUpdateCartItemPayload(4)).toEqual({ quantity: 4 });
    expect(buildUpdateCartItemPayload(0)).toEqual({ quantity: 1 });
  });
});

describe('mapCart / mapCartItem', () => {
  const rawItem = {
    id: 'item-1',
    product_id: 'prod-1',
    product_variant_id: 'var-1',
    quantity: 2,
    unit_price: '15000',
    subtotal: '30000',
    currency: 'TZS',
    available_stock: 5,
    shipping_method: 'air',
    shipping_price: '2000',
    product: {
      id: 'prod-1',
      slug: 'gown',
      name: 'Evening Gown',
      commerce_channel_code: 'CHINA_IMPORT',
      commerce_source_label: 'Imported From China',
      primary_image: { url: 'https://cdn.example/g.jpg' },
    },
    variant: {
      id: 'var-1',
      name: 'Black / 128GB',
      sku: 'GOWN-B-128',
      display_attributes: [
        { attribute: 'Color', value: 'Black' },
        { attribute: 'Storage', value: '128GB' },
      ],
    },
  };

  it('maps Contract v1 cart lines without inventing totals', () => {
    const item = mapCartItem(rawItem);
    expect(item).toMatchObject({
      id: 'item-1',
      productId: 'prod-1',
      productVariantId: 'var-1',
      quantity: 2,
      unitPrice: '15000',
      lineSubtotal: '30000',
      productName: 'Evening Gown',
      journeyLabel: 'Order from China',
      commerceChannelCode: 'CHINA_IMPORT',
      imageUrl: 'https://cdn.example/g.jpg', // product fallback when variant has no image
      displayAttributes: [
        { attribute: 'Color', value: 'Black' },
        { attribute: 'Storage', value: '128GB' },
      ],
    });
  });

  it('uses each selected variant image for the same product', () => {
    const product = {
      id: 'prod-1',
      slug: 'gown',
      name: 'Evening Gown',
      commerce_channel_code: 'CHINA_IMPORT',
      primary_image: { url: 'https://cdn.example/product.jpg' },
    };

    const variantA = mapCartItem({
      ...rawItem,
      id: 'item-a',
      product_variant_id: 'var-a',
      product,
      variant: {
        id: 'var-a',
        name: 'Black',
        primary_image: { url: 'https://cdn.example/variant-a.jpg' },
      },
    });
    const variantB = mapCartItem({
      ...rawItem,
      id: 'item-b',
      product_variant_id: 'var-b',
      product,
      variant: {
        id: 'var-b',
        name: 'Red',
        primary_image: { url: 'https://cdn.example/variant-b.jpg' },
      },
    });

    expect(variantA?.imageUrl).toBe('https://cdn.example/variant-a.jpg');
    expect(variantB?.imageUrl).toBe('https://cdn.example/variant-b.jpg');
    expect(variantA?.imageUrl).not.toBe(variantB?.imageUrl);
  });

  it('falls back to the product image when the variant has no image', () => {
    const item = mapCartItem({
      ...rawItem,
      product: {
        ...rawItem.product,
        primary_image: { url: 'https://cdn.example/product-only.jpg' },
      },
      variant: {
        ...rawItem.variant,
      },
    });
    expect(item?.imageUrl).toBe('https://cdn.example/product-only.jpg');
  });

  it('prefers display_url for cart line images', () => {
    const item = mapCartItem({
      ...rawItem,
      product: {
        ...rawItem.product,
        primary_image: {
          url: 'https://cdn.example/original.jpg',
          display_url: 'https://cdn.example/display.webp',
        },
      },
    });

    expect(item?.imageUrl).toBe('https://cdn.example/display.webp');
  });

  it('maps full cart resource including server totals', () => {
    const cart = mapCart({
      id: 'cart-1',
      status: 'active',
      currency: 'TZS',
      items: [rawItem],
      item_count: 2,
      is_empty: false,
      subtotal: '30000',
      total: '30000',
    });

    expect(cart.items).toHaveLength(1);
    expect(cart).toMatchObject({
      id: 'cart-1',
      itemCount: 2,
      isEmpty: false,
      subtotal: '30000',
      total: '30000',
    });
  });

  it('maps TZ_LOCAL journey label from channel code', () => {
    const item = mapCartItem({
      ...rawItem,
      product: {
        ...rawItem.product,
        commerce_channel_code: 'TZ_LOCAL',
        commerce_source_label: 'Local Store',
      },
    });
    expect(item?.journeyLabel).toBe('Buy from TZ');
  });

  it('returns null for incomplete cart lines', () => {
    expect(mapCartItem({ quantity: 1 })).toBeNull();
  });
});

describe('mapCartSummary', () => {
  it('maps Contract v1 cart resource fields', () => {
    expect(
      mapCartSummary({
        id: 'cart-1',
        status: 'active',
        currency: 'TZS',
        item_count: 2,
        is_empty: false,
        subtotal: '1000',
        total: '1000',
        items: [],
      }),
    ).toMatchObject({
      id: 'cart-1',
      itemCount: 2,
      isEmpty: false,
      currency: 'TZS',
    });
  });
});

describe('remove item path', () => {
  it('uses item id in DELETE path construction (encodeURIComponent-safe)', () => {
    const itemId = 'item-with/special';
    expect(`/cart/items/${encodeURIComponent(itemId)}`).toBe(
      '/cart/items/item-with%2Fspecial',
    );
  });
});
