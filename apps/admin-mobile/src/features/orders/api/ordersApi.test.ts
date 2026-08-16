import { buildOrdersQuery, mapOrder, mapOrdersList } from './ordersApi';

describe('ordersApi', () => {
  it('builds query with status, channel, search, and page', () => {
    expect(
      buildOrdersQuery({
        status: 'paid',
        commerce_channel: 'CHINA_IMPORT',
        q: '  ORD-100  ',
        page: 2,
      }),
    ).toEqual({
      status: 'paid',
      commerce_channel: 'CHINA_IMPORT',
      q: 'ORD-100',
      page: 2,
    });
  });

  it('omits all status and empty search', () => {
    expect(buildOrdersQuery({ status: 'all', q: '   ' })).toEqual({});
  });

  it('maps order list items', () => {
    const orders = mapOrdersList([
      {
        id: 'o1',
        order_number: 'ORD-001',
        commerce_channel_code: 'TZ_LOCAL',
        status: 'paid',
        status_label: 'Paid',
        total: 10000,
        user: { name: 'Jane', email: 'jane@test.com' },
      },
    ]);

    expect(orders[0].order_number).toBe('ORD-001');
    expect(orders[0].user?.name).toBe('Jane');
  });

  it('maps order detail', () => {
    const order = mapOrder({
      id: 'o1',
      order_number: 'ORD-001',
      status: 'paid',
      items: [{ product_name: 'Widget', quantity: 2 }],
      payments: [{ status: 'completed', amount: 10000 }],
    });

    expect(order.items?.[0]?.product_name).toBe('Widget');
    expect(order.payments?.[0]?.amount).toBe(10000);
  });
});
