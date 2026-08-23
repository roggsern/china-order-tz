import { apiClient } from '@/src/core/api';
import { ApiError } from '@/src/core/errors';
import { clearCart } from './cartApi';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    delete: jest.fn(),
  },
}));

const mockDelete = apiClient.delete as jest.MockedFunction<typeof apiClient.delete>;

const emptyCartResource = {
  id: 'cart-1',
  status: 'active',
  currency: 'TZS',
  items: [],
  item_count: 0,
  is_empty: true,
  subtotal: '0',
  total: '0',
};

describe('clearCart', () => {
  beforeEach(() => {
    mockDelete.mockReset();
  });

  it('clears the authenticated server cart and maps the empty resource', async () => {
    mockDelete.mockResolvedValue({ data: emptyCartResource } as never);

    const cart = await clearCart();

    expect(mockDelete).toHaveBeenCalledWith('/cart/clear');
    expect(cart.isEmpty).toBe(true);
    expect(cart.items).toEqual([]);
  });

  it('does not invent an empty cart when the server clear fails', async () => {
    mockDelete.mockRejectedValue(
      new ApiError({
        message: 'Unable to clear cart.',
        status: 500,
        code: 'server_error',
      }),
    );

    await expect(clearCart()).rejects.toBeInstanceOf(ApiError);
  });

  it('treats a repeated clear as idempotent when the backend returns empty', async () => {
    mockDelete.mockResolvedValue({ data: emptyCartResource } as never);

    const first = await clearCart();
    const second = await clearCart();

    expect(first.isEmpty).toBe(true);
    expect(second.isEmpty).toBe(true);
    expect(mockDelete).toHaveBeenCalledTimes(2);
  });
});
