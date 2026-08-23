import { resolveCartAfterClearAttempt } from './clearCartState';
import type { Cart } from '../models/types';

const filled: Cart = {
  id: 'cart-1',
  status: 'active',
  currency: 'TZS',
  items: [
    {
      id: 'item-1',
      productId: 'p1',
      productVariantId: null,
      quantity: 1,
      unitPrice: '10',
      lineSubtotal: '10',
      currency: 'TZS',
      availableStock: 2,
      shippingMethod: null,
      shippingPrice: null,
      productName: 'Hat',
      productSlug: 'hat',
      imageUrl: null,
      commerceChannelCode: 'TZ_LOCAL',
      commerceSourceLabel: null,
      journeyLabel: 'Buy from TZ',
      variantName: null,
      variantSku: null,
      displayAttributes: [],
    },
  ],
  itemCount: 1,
  isEmpty: false,
  subtotal: '10',
  total: '10',
};

const empty: Cart = {
  ...filled,
  items: [],
  itemCount: 0,
  isEmpty: true,
  subtotal: '0',
  total: '0',
};

describe('resolveCartAfterClearAttempt', () => {
  it('applies the server cart after a successful clear', () => {
    expect(
      resolveCartAfterClearAttempt({
        previous: filled,
        serverCart: empty,
        succeeded: true,
      }),
    ).toEqual(empty);
  });

  it('keeps the previous cart when the server clear fails', () => {
    expect(
      resolveCartAfterClearAttempt({
        previous: filled,
        serverCart: empty,
        succeeded: false,
      }),
    ).toEqual(filled);
  });

  it('is idempotent when the server already returned an empty cart', () => {
    expect(
      resolveCartAfterClearAttempt({
        previous: empty,
        serverCart: empty,
        succeeded: true,
      }),
    ).toEqual(empty);
  });
});
