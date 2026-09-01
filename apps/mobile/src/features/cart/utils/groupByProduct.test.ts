import { groupCartLinesByProductId } from './groupByProduct';
import type { CartItem } from '../models/types';

function line(overrides: Partial<CartItem>): CartItem {
  return {
    id: 'line-1',
    productId: 'product-a',
    productVariantId: null,
    quantity: 1,
    unitPrice: 8000,
    lineSubtotal: 8000,
    currency: 'TZS',
    availableStock: 20,
    shippingMethod: null,
    shippingPrice: null,
    productName: 'Blouse',
    productSlug: 'blouse',
    imageUrl: null,
    commerceChannelCode: 'TZ_LOCAL',
    commerceSourceLabel: null,
    journeyLabel: 'Buy from TZ',
    variantName: null,
    variantSku: null,
    displayAttributes: [],
    ...overrides,
  };
}

describe('groupCartLinesByProductId', () => {
  it('keeps same product_id together for sibling variants', () => {
    const grouped = groupCartLinesByProductId([
      line({ id: 'red', productId: 'blouse-a', variantName: 'Red XL', quantity: 2 }),
      line({ id: 'other', productId: 'skirt-b', productName: 'Skirt', quantity: 2 }),
      line({ id: 'blue', productId: 'blouse-a', variantName: 'Blue M', quantity: 2 }),
    ]);

    expect(grouped).toHaveLength(2);
    expect(grouped[0]?.map((item) => item.id)).toEqual(['red', 'blue']);
    expect(grouped[1]?.map((item) => item.id)).toEqual(['other']);
  });

  it('never groups by variant, store, or channel', () => {
    const grouped = groupCartLinesByProductId([
      line({
        id: 'red',
        productId: 'blouse-a',
        productVariantId: 'var-red',
        commerceChannelCode: 'CHINA_IMPORT',
      }),
      line({
        id: 'blue',
        productId: 'blouse-a',
        productVariantId: 'var-blue',
        commerceChannelCode: 'CHINA_IMPORT',
      }),
      line({
        id: 'other',
        productId: 'skirt-b',
        productVariantId: 'var-red',
        commerceChannelCode: 'CHINA_IMPORT',
      }),
    ]);

    expect(grouped).toHaveLength(2);
    expect(grouped[0]?.map((item) => item.productVariantId)).toEqual([
      'var-red',
      'var-blue',
    ]);
    expect(grouped[1]?.[0]?.productId).toBe('skirt-b');
  });
});
