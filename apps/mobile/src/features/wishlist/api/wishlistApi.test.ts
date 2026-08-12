import { mapWishlistItem } from './wishlistApi';

describe('mapWishlistItem', () => {
  it('maps server wishlist rows without inventing product media', () => {
    expect(
      mapWishlistItem({
        id: 'w1',
        product_id: 'p1',
        product_variant_id: null,
        product: { id: 'p1', slug: 'collared-shirt-dress', name: 'COLLARED SHIRT DRESS' },
        created_at: '2026-01-01T00:00:00Z',
      }),
    ).toEqual({
      id: 'w1',
      productId: 'p1',
      productVariantId: null,
      productSlug: 'collared-shirt-dress',
      productName: 'COLLARED SHIRT DRESS',
      createdAt: '2026-01-01T00:00:00Z',
    });
  });

  it('returns null for incomplete rows', () => {
    expect(mapWishlistItem({ id: 'w1' })).toBeNull();
  });
});
