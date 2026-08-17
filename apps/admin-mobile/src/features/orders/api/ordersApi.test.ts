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

  it('maps order list with Laravel decimal-string money', () => {
    const orders = mapOrdersList([
      {
        id: 'o1',
        order_number: 'ORD-001',
        commerce_channel_code: 'TZ_LOCAL',
        status: 'paid',
        status_label: 'Paid',
        total: '10000.00',
        grand_total: '10000.00',
        currency: 'TZS',
        user: { name: 'Jane', email: 'jane@test.com' },
        items: [
          {
            product_name: 'Widget',
            quantity: 2,
            unit_price: '5000.00',
            line_total: '10000.00',
          },
        ],
        payments: [{ status: 'completed', amount: '10000.00', method: 'mpesa' }],
      },
    ]);

    expect(orders[0].order_number).toBe('ORD-001');
    expect(orders[0].total).toBe(10000);
    expect(orders[0].grand_total).toBe(10000);
    expect(orders[0].items?.[0]?.unit_price).toBe(5000);
    expect(orders[0].items?.[0]?.line_total).toBe(10000);
    expect(orders[0].payments?.[0]?.amount).toBe(10000);
  });

  it('maps order detail with numeric money compatibility', () => {
    const order = mapOrder({
      id: 'o1',
      order_number: 'ORD-001',
      status: 'paid',
      total: 58000,
      grand_total: 58000,
      items: [{ product_name: 'Widget', quantity: 2, unit_price: 29000, line_total: 58000 }],
      payments: [{ status: 'completed', amount: 58000 }],
    });

    expect(order.grand_total).toBe(58000);
    expect(order.items?.[0]?.product_name).toBe('Widget');
    expect(order.payments?.[0]?.amount).toBe(58000);
  });

  it('allows null nested money where backend permits null', () => {
    const order = mapOrder({
      id: 'o1',
      order_number: 'ORD-002',
      status: 'pending',
      total: '0.00',
      grand_total: '0.00',
      items: [{ product_name: 'Draft', quantity: 1, unit_price: null, line_total: null }],
      payments: [{ status: 'pending', amount: null }],
    });

    expect(order.items?.[0]?.unit_price).toBeNull();
    expect(order.items?.[0]?.line_total).toBeNull();
    expect(order.payments?.[0]?.amount).toBeNull();
  });

  it('rejects malformed order money', () => {
    expect(() =>
      mapOrder({
        id: 'o1',
        order_number: 'ORD-BAD',
        status: 'paid',
        grand_total: 'not-a-number',
      }),
    ).toThrow();

    expect(() =>
      mapOrder({
        id: 'o1',
        order_number: 'ORD-BAD',
        status: 'paid',
        items: [{ unit_price: '12.34.56' }],
      }),
    ).toThrow();
  });
});
