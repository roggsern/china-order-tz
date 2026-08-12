import { useMemo } from 'react';
import { useCart } from '@/src/features/cart/hooks/useCart';
import { useOrdersList } from '@/src/features/orders/hooks/useOrders';
import { useUnreadNotificationCount } from '@/src/features/notifications';
import { countPayableOrders, formatTabBadgeCount } from './tabBadges';

/**
 * Shell badge counts from cart/orders + backend notification unread authority.
 */
export function useShellTabBadges(): {
  cartBadge?: string;
  ordersBadge?: string;
  accountBadge?: string;
} {
  const cartQuery = useCart();
  const ordersQuery = useOrdersList({ filter: 'all', perPage: 10 });
  const unreadQuery = useUnreadNotificationCount();

  return useMemo(() => {
    const cartCount = cartQuery.data?.itemCount ?? 0;
    const orders =
      ordersQuery.data?.pages.flatMap((page) => page.orders) ?? [];
    const payable = countPayableOrders(orders);
    const unread = unreadQuery.data ?? 0;

    return {
      cartBadge: formatTabBadgeCount(cartCount),
      ordersBadge: formatTabBadgeCount(payable),
      accountBadge: formatTabBadgeCount(unread),
    };
  }, [
    cartQuery.data?.itemCount,
    ordersQuery.data?.pages,
    unreadQuery.data,
  ]);
}
