import { mapProductQuote } from '@/src/features/product/map/mapProduct';
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
    expect(cart.purchaseQuantityBlockers).toEqual([]);
    expect(cart.items[0]?.purchaseQuantity ?? null).toBeNull();
  });

  it('maps purchase_quantity lines and one blocker per product', () => {
    const cart = mapCart({
      id: 'cart-1',
      purchase_quantity_blockers: [
        {
          product_id: 'prod-1',
          minimum_quantity: 6,
          increment: null,
          eligible_quantity: 4,
          minimum_satisfied: false,
          increment_satisfied: true,
          quantity_to_minimum: 2,
          next_legal_quantity: 6,
          blocks_checkout: true,
        },
        {
          product_id: 'prod-1',
          minimum_quantity: 6,
          increment: null,
          eligible_quantity: 4,
          minimum_satisfied: false,
          increment_satisfied: true,
          quantity_to_minimum: 2,
          next_legal_quantity: 6,
          blocks_checkout: true,
        },
      ],
      items: [
        {
          ...rawItem,
          id: 'item-red',
          product_variant_id: 'var-red',
          purchase_quantity: {
            minimum_quantity: 6,
            increment: null,
            eligible_quantity: 4,
            aggregates_variants: true,
            minimum_satisfied: false,
            increment_satisfied: true,
            quantity_to_minimum: 2,
            next_legal_quantity: 6,
            construction_complete: false,
            blocks_checkout: true,
          },
        },
        {
          ...rawItem,
          id: 'item-blue',
          product_variant_id: 'var-blue',
          purchase_quantity: {
            minimum_quantity: 6,
            increment: null,
            eligible_quantity: 4,
            aggregates_variants: true,
            minimum_satisfied: false,
            increment_satisfied: true,
            quantity_to_minimum: 2,
            next_legal_quantity: 6,
            construction_complete: false,
            blocks_checkout: true,
          },
        },
      ],
    });

    expect(cart.items).toHaveLength(2);
    expect(cart.items[0]?.productId).toBe('prod-1');
    expect(cart.items[0]?.purchaseQuantity?.eligible_quantity).toBe(4);
    expect(cart.purchaseQuantityBlockers).toHaveLength(1);
    expect(cart.purchaseQuantityBlockers[0]?.product_id).toBe(
      cart.items[0]?.productId,
    );
    expect(cart.items[0]?.productId).not.toBe(cart.items[0]?.productVariantId);
    expect(cart.items[1]?.productId).toBe('prod-1');
    expect(cart.items[1]?.productVariantId).toBe('var-blue');
  });

  it('maps product_id as catalog identity for China and TZ, simple and configurable', () => {
    const chinaSimple = mapCartItem({
      ...rawItem,
      product_variant_id: null,
      variant: null,
    });
    expect(chinaSimple?.productId).toBe('prod-1');
    expect(chinaSimple?.productVariantId).toBeNull();
    expect(chinaSimple?.commerceChannelCode).toBe('CHINA_IMPORT');

    const chinaConfigurable = mapCartItem(rawItem);
    expect(chinaConfigurable?.productId).toBe('prod-1');
    expect(chinaConfigurable?.productVariantId).toBe('var-1');
    expect(chinaConfigurable?.productId).not.toBe('var-1');

    const tzSimple = mapCartItem({
      ...rawItem,
      product_variant_id: null,
      variant: null,
      product: {
        ...rawItem.product,
        commerce_channel_code: 'TZ_LOCAL',
      },
    });
    expect(tzSimple?.productId).toBe('prod-1');
    expect(tzSimple?.commerceChannelCode).toBe('TZ_LOCAL');

    const tzConfigurable = mapCartItem({
      ...rawItem,
      product: {
        ...rawItem.product,
        commerce_channel_code: 'TZ_LOCAL',
      },
    });
    expect(tzConfigurable?.productId).toBe('prod-1');
    expect(tzConfigurable?.productVariantId).toBe('var-1');
    expect(tzConfigurable?.productId).not.toBe(tzConfigurable?.productVariantId);
  });

  it('malformed purchase_quantity degrades without breaking the cart', () => {
    const cart = mapCart({
      id: 'cart-1',
      purchase_quantity_blockers: [{ product_id: 'prod-1', minimum_quantity: '6.5' }],
      items: [
        {
          ...rawItem,
          purchase_quantity: { minimum_quantity: 6 },
        },
      ],
    });

    expect(cart.items).toHaveLength(1);
    expect(cart.items[0]?.purchaseQuantity ?? null).toBeNull();
    expect(cart.purchaseQuantityBlockers).toEqual([]);
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

  it('preserves server volume_pricing on cart lines', () => {
    const item = mapCartItem({
      ...rawItem,
      unit_price: '8000.00',
      volume_pricing: {
        eligible_quantity: 10,
        aggregates_variants: true,
        current_tier: {
          min_quantity: 10,
          unit_price: '8000.00',
          type: 'fixed_unit',
          discount_percent: null,
          scope: 'product',
        },
        next_tier: null,
        quantity_to_next_tier: null,
        base_unit_price: '10000.00',
        resolved_unit_price: '8000.00',
        savings_per_unit: '2000.00',
        savings_total: '4000.00',
        currency: 'TZS',
        tiers: [
          {
            min_quantity: 10,
            unit_price: '8000.00',
            type: 'fixed_unit',
            discount_percent: null,
            scope: 'product',
          },
        ],
      },
    });

    expect(item?.unitPrice).toBe('8000.00');
    expect(item?.volumePricing?.eligible_quantity).toBe(10);
    expect(item?.volumePricing?.savings_total).toBe('4000.00');
    expect(item?.volumePricing?.resolved_unit_price).toBe('8000.00');
  });

  it('keeps cart unit price server-owned and equal to the quote for the same payload', () => {
    const volumePricing = {
      eligible_quantity: 10,
      aggregates_variants: false,
      current_tier: {
        min_quantity: 10,
        unit_price: '8000.00',
        type: 'fixed_unit',
        discount_percent: null,
        scope: 'product',
      },
      next_tier: null,
      quantity_to_next_tier: null,
      base_unit_price: '10000.00',
      resolved_unit_price: '8000.00',
      savings_per_unit: '2000.00',
      savings_total: '20000.00',
      currency: 'TZS',
      tiers: [
        {
          min_quantity: 10,
          unit_price: '8000.00',
          type: 'fixed_unit',
          discount_percent: null,
          scope: 'product',
        },
      ],
    };
    const quote = mapProductQuote({
      product_id: 'prod-1',
      configuration_id: null,
      quantity: 10,
      currency: 'TZS',
      unit_price: '8000.00',
      line_total: '80000.00',
      volume_pricing: volumePricing,
    });
    const cartItem = mapCartItem({
      ...rawItem,
      quantity: 10,
      unit_price: '8000.00',
      subtotal: '80000.00',
      volume_pricing: volumePricing,
    });

    expect(quote?.unitPrice).toBe('8000.00');
    expect(cartItem?.unitPrice).toBe(quote?.unitPrice);
    expect(cartItem?.volumePricing?.resolved_unit_price).toBe(
      quote?.volumePricing?.resolved_unit_price,
    );
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
