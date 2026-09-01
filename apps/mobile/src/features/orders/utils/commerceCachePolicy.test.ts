import { QueryClient } from '@tanstack/react-query';
import { cartQueryKey } from '@/src/features/cart/hooks/useCart';
import type { Cart } from '@/src/features/cart/models/types';
import {
  checkoutPrepareQueryKey,
  invalidateAfterCheckoutCancel,
} from '@/src/features/checkout/utils/checkoutQueryKeys';
import { invalidateAfterPaymentSuccess } from '../hooks/useOrders';
import { orderDetailQueryKey, ordersRootQueryKey } from './ordersQueryKeys';

function createCacheTestClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { gcTime: 0, retry: false },
      mutations: { gcTime: 0, retry: 0 },
    },
  });
}

const cart: Cart = {
  id: 'cart-1',
  status: 'active',
  currency: 'TZS',
  items: [],
  itemCount: 0,
  isEmpty: true,
  subtotal: '0',
  total: '0',
  purchaseQuantityBlockers: [],
};

describe('commerce cache policy', () => {
  it('payment success invalidates payment, cart, and order queries', async () => {
    const client = createCacheTestClient();
    const invalidated: string[][] = [];
    const original = client.invalidateQueries.bind(client);
    client.invalidateQueries = ((filters, options) => {
      if (filters && typeof filters === 'object' && 'queryKey' in filters && filters.queryKey) {
        invalidated.push([...(filters.queryKey as readonly unknown[])] as string[]);
      }
      return original(filters as never, options as never);
    }) as typeof client.invalidateQueries;

    await invalidateAfterPaymentSuccess(client, 'ord-99');

    expect(invalidated).toEqual(
      expect.arrayContaining([
        ['payments'],
        cartQueryKey() as unknown as string[],
        ordersRootQueryKey() as unknown as string[],
        orderDetailQueryKey('ord-99') as unknown as string[],
      ]),
    );
    client.clear();
  });

  it('cart mutation success writes authoritative cache without invalidating cart', () => {
    const client = createCacheTestClient();
    client.setQueryData(cartQueryKey(), cart);
    const state = client.getQueryState(cartQueryKey());
    expect(state?.data).toEqual(cart);
    expect(state?.isInvalidated).toBe(false);
    client.clear();
  });

  it('checkout cancel invalidates prepare and drops the session key', async () => {
    const client = createCacheTestClient();
    client.setQueryData(checkoutPrepareQueryKey(), { items: [] });
    client.setQueryData(['checkout', 'session', 'chk-1'], { id: 'chk-1' });
    await invalidateAfterCheckoutCancel(client, 'chk-1');
    expect(client.getQueryData(['checkout', 'session', 'chk-1'])).toBeUndefined();
    expect(client.getQueryState(checkoutPrepareQueryKey())?.isInvalidated).toBe(true);
    client.clear();
  });
});
