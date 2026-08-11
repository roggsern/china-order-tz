import {
  orderDetailQueryKey,
  orderTrackingQueryKey,
  ordersListQueryKey,
  ordersRootQueryKey,
} from './ordersQueryKeys';

describe('orders query keys', () => {
  it('nests list/detail/tracking under the orders root for refresh after mutations', () => {
    expect(ordersRootQueryKey()).toEqual(['orders']);
    expect(ordersListQueryKey('all')[0]).toBe('orders');
    expect(orderDetailQueryKey('ord-1')).toEqual(['orders', 'detail', 'ord-1']);
    expect(orderTrackingQueryKey('ord-1')).toEqual([
      'orders',
      'tracking',
      'ord-1',
    ]);
  });
});
