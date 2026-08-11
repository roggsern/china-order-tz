import { useMemo } from 'react';
import { useCart } from '@/src/features/cart/hooks/useCart';
import { useOrdersList } from '@/src/features/orders/hooks/useOrders';
import { countPayableOrders, formatTabBadgeCount } from './tabBadges';

/**
 * Shell badge counts from existing cart/orders queries.
 * Does not invent totals — uses mapped cart.itemCount and list pages already fetched.
 */
export function useShellTabBadges(): {
  cartBadge?: string;
  ordersBadge?: string;
} {
  const cartQuery = useCart();
  const ordersQuery = useOrdersList({ filter: 'all', perPage: 10 });

  return useMemo(() => {
    const cartCount = cartQuery.data?.itemCount ?? 0;
    const orders =
      ordersQuery.data?.pages.flatMap((page) => page.orders) ?? [];
    const payable = countPayableOrders(orders);

    return {
      cartBadge: formatTabBadgeCount(cartCount),
      ordersBadge: formatTabBadgeCount(payable),
    };
  }, [cartQuery.data?.itemCount, ordersQuery.data?.pages]);
}
