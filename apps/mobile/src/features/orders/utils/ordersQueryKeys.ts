import type { OrdersListFilter } from '../models/types';

export function ordersListQueryKey(filter: OrdersListFilter = 'all') {
  return ['orders', 'list', filter] as const;
}

export function orderDetailQueryKey(orderId: string) {
  return ['orders', 'detail', orderId] as const;
}

export function orderTrackingQueryKey(orderId: string) {
  return ['orders', 'tracking', orderId] as const;
}

export function ordersRootQueryKey() {
  return ['orders'] as const;
}
