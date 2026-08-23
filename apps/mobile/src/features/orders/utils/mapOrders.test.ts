import {
  buildCancelOrderPayload,
  isOrdersListEmpty,
  journeyLabelFromOrderSource,
  mapOrderDetail,
  mapOrderListItem,
  mapOrderTracking,
  mapOrdersListPage,
  normalizeOrdersFilter,
  shouldOfferCancel,
} from './mapOrders';

describe('journeyLabelFromOrderSource', () => {
  it('maps China and Dar without inventing channels', () => {
    expect(journeyLabelFromOrderSource('China')).toBe('Order from China');
    expect(journeyLabelFromOrderSource('Dar')).toBe('Buy from TZ');
    expect(journeyLabelFromOrderSource(null)).toBeNull();
  });
});

describe('mapOrderListItem', () => {
  it('maps CustomerOrderResource list fields from the server', () => {
    const order = mapOrderListItem({
      id: 'ord-1',
      order_number: 'COTZ-1001',
      source: 'China',
      status: 'paid',
      status_label: 'Order confirmed',
      payment_status: 'paid',
      currency: 'TZS',
      subtotal: '50000',
      grand_total: '55000',
      total: '55000',
      created_at: '2026-08-10T10:00:00Z',
      preview: {
        item_count: 2,
        total_quantity: 3,
        primary_item: {
          name: 'Gown',
          image_url: 'https://cdn.example/gown.jpg',
          quantity: 2,
        },
        extra_items: 1,
      },
      progress: {
        current_key: 'paid',
        current_label: 'Order confirmed',
        steps: [
          { key: 'placed', label: 'Placed', completed: true },
          { key: 'paid', label: 'Paid', completed: true },
        ],
      },
    });

    expect(order).toMatchObject({
      id: 'ord-1',
      orderNumber: 'COTZ-1001',
      source: 'China',
      journeyLabel: 'Order from China',
      status: 'paid',
      statusLabel: 'Order confirmed',
      paymentStatus: 'paid',
      grandTotal: '55000',
      currency: 'TZS',
    });
    expect(order?.preview?.primaryItem?.name).toBe('Gown');
    expect(order?.preview?.primaryItem?.imageUrl).toBe(
      'https://cdn.example/gown.jpg',
    );
    expect(order?.progress?.currentLabel).toBe('Order confirmed');
    expect(order?.canCancel).toBeNull();
    expect(order?.canPay).toBeNull();
    expect(order?.activePaymentTransaction).toBeNull();
  });

  it('absolutizes production-shaped relative storage image_url for list cards', () => {
    const order = mapOrderListItem({
      id: 'ord-rel',
      order_number: 'COTZ-REL',
      status: 'paid',
      preview: {
        item_count: 1,
        total_quantity: 1,
        primary_item: {
          name: 'Tie-Front Blouse',
          image_url: '/storage/products/blouse.jpg',
          quantity: 1,
        },
        extra_items: 0,
      },
    });

    expect(order?.preview?.primaryItem?.imageUrl).toMatch(
      /\/storage\/products\/blouse\.jpg$/,
    );
    expect(order?.preview?.primaryItem?.imageUrl?.startsWith('http')).toBe(true);
  });

  it('reads can_cancel only when the server provides it', () => {
    expect(
      mapOrderListItem({
        id: 'ord-2',
        can_cancel: true,
        status: 'processing',
      })?.canCancel,
    ).toBe(true);
  });

  it('reads can_pay only when the server provides it', () => {
    expect(
      mapOrderListItem({
        id: 'ord-pay',
        can_pay: true,
        status: 'pending_payment',
      })?.canPay,
    ).toBe(true);
    expect(
      mapOrderListItem({
        id: 'ord-unpay',
        can_pay: false,
        status: 'pending_payment',
      })?.canPay,
    ).toBe(false);
  });

  it('maps active_payment_transaction from the backend only', () => {
    const withActive = mapOrderListItem({
      id: 'ord-active',
      status: 'pending_payment',
      can_pay: true,
      active_payment_transaction: {
        id: 'txn-snippe-1',
        status: 'processing',
        provider: 'snippe',
      },
    });

    expect(withActive?.activePaymentTransaction).toEqual({
      id: 'txn-snippe-1',
      status: 'processing',
      provider: 'snippe',
    });

    const missing = mapOrderListItem({
      id: 'ord-no-txn',
      status: 'pending_payment',
      can_pay: true,
    });
    expect(missing?.activePaymentTransaction).toBeNull();

    const empty = mapOrderListItem({
      id: 'ord-empty-txn',
      status: 'pending_payment',
      active_payment_transaction: {},
    });
    expect(empty?.activePaymentTransaction).toBeNull();
  });
});

describe('mapOrdersListPage pagination', () => {
  it('maps meta and links into next-page helpers', () => {
    const page = mapOrdersListPage({
      data: [
        { id: 'ord-1', order_number: 'A', source: 'Dar', status: 'paid' },
        { id: 'ord-2', order_number: 'B', source: 'China', status: 'shipped' },
      ],
      meta: {
        current_page: 1,
        last_page: 3,
        per_page: 2,
        total: 5,
      },
      links: {
        first: 'https://api.example/orders?page=1',
        last: 'https://api.example/orders?page=3',
        prev: null,
        next: 'https://api.example/orders?page=2',
      },
    });

    expect(page.orders).toHaveLength(2);
    expect(page.page).toBe(1);
    expect(page.lastPage).toBe(3);
    expect(page.total).toBe(5);
    expect(page.hasNextPage).toBe(true);
    expect(page.nextPage).toBe(2);
    expect(page.orders[1]?.journeyLabel).toBe('Order from China');
  });

  it('marks empty lists without inventing orders', () => {
    const page = mapOrdersListPage({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
    });
    expect(isOrdersListEmpty(page)).toBe(true);
    expect(page.hasNextPage).toBe(false);
    expect(page.nextPage).toBeNull();
  });
});

describe('mapOrderDetail', () => {
  it('maps items, summary, payment, and progress from detail resource', () => {
    const detail = mapOrderDetail({
      id: 'ord-9',
      order_number: 'COTZ-2002',
      source: 'Dar',
      status: 'processing',
      status_label: 'Being prepared',
      created_at: '2026-08-10T12:00:00Z',
      items: [
        {
          id: 'line-1',
          product_id: 'prod-1',
          product_variant_id: 'var-1',
          product_name: 'Sneakers',
          variant_name_snapshot: 'Red / 42',
          attributes_snapshot: [
            { attribute: 'Color', value: 'Red' },
            { attribute: 'Size', value: '42' },
          ],
          quantity: 1,
          unit_price: '80000.00',
          line_total: '80000.00',
          currency: 'TZS',
          product_image_snapshot: 'https://cdn.example/sneakers.jpg',
        },
      ],
      summary: {
        subtotal: '80000',
        shipping: '5000',
        shipping_total: '5000',
        discount_total: '0',
        tax_total: '0',
        grand_total: '85000',
        total: '85000',
      },
      payment: {
        payment_status: 'paid',
        payment_method: 'nmb',
        provider: 'nmb',
        reference: 'COTZ-PAY-9',
        amount: '85000.00',
        currency: 'TZS',
        paid_at: '2026-08-10T12:05:00Z',
      },
      progress: {
        current_key: 'processing',
        current_label: 'Being prepared',
        steps: [{ key: 'processing', label: 'Being prepared', completed: false }],
      },
      shipment: { status: 'Being prepared' },
    });

    expect(detail.orderNumber).toBe('COTZ-2002');
    expect(detail.journeyLabel).toBe('Buy from TZ');
    expect(detail.items[0]).toMatchObject({
      productName: 'Sneakers',
      variantName: 'Red / 42',
      quantity: 1,
      lineTotal: '80000.00',
      imageUrl: 'https://cdn.example/sneakers.jpg',
    });
    expect(detail.items[0]?.attributes).toEqual([
      { attribute: 'Color', value: 'Red' },
      { attribute: 'Size', value: '42' },
    ]);
    expect(detail.summary.grandTotal).toBe('85000');
    expect(detail.payment?.paymentStatus).toBe('paid');
    expect(detail.payment?.reference).toBe('COTZ-PAY-9');
    expect(detail.shipment?.status).toBe('Being prepared');
    expect(detail.canPay).toBeNull();
    expect(detail.activePaymentTransaction).toBeNull();
  });

  it('maps can_pay and active_payment_transaction on detail', () => {
    const detail = mapOrderDetail({
      id: 'ord-recover',
      status: 'pending_payment',
      can_pay: true,
      active_payment_transaction: {
        id: 'txn-nmb-9',
        status: 'pending',
        provider: 'nmb',
      },
      items: [],
      summary: { grand_total: '1000' },
    });

    expect(detail.canPay).toBe(true);
    expect(detail.activePaymentTransaction).toEqual({
      id: 'txn-nmb-9',
      status: 'pending',
      provider: 'nmb',
    });
  });

  it('absolutizes detail product_image_snapshot relative storage paths', () => {
    const detail = mapOrderDetail({
      id: 'ord-rel',
      order_number: 'COTZ-REL',
      status: 'paid',
      items: [
        {
          id: 'line-rel',
          product_name: 'Blouse',
          quantity: 1,
          product_image_snapshot: '/storage/products/blouse.jpg',
        },
      ],
      summary: { grand_total: '1000' },
    });

    expect(detail.items[0]?.imageUrl).toMatch(/\/storage\/products\/blouse\.jpg$/);
    expect(detail.items[0]?.imageUrl?.startsWith('http')).toBe(true);
  });
});

describe('mapOrderTracking', () => {
  it('maps shipment and timeline fields without inventing statuses', () => {
    const tracking = mapOrderTracking({
      order_number: 'COTZ-2002',
      current_status: 'in_transit',
      current_status_label: 'On the way',
      source: 'company_shipment',
      tracking_ownership: 'company_shipment',
      shipment: {
        id: 'ship-1',
        status: 'in_transit',
        status_label: 'In transit',
        carrier_name: 'DHL',
        tracking_reference: 'DHL-9988',
        transport_mode_label: 'Air',
      },
      timeline: [
        {
          key: 'shipped',
          label: 'Shipped',
          completed: true,
          completed_at: '2026-08-11T08:00:00Z',
        },
        {
          key: 'in_transit',
          label: 'In transit',
          completed: false,
        },
      ],
      unified_timeline: [],
      progress: {
        current_key: 'in_transit',
        current_label: 'On the way',
        steps: [],
      },
    });

    expect(tracking.currentStatusLabel).toBe('On the way');
    expect(tracking.shipment).toMatchObject({
      carrierName: 'DHL',
      trackingReference: 'DHL-9988',
    });
    expect(tracking.timeline).toHaveLength(2);
    expect(tracking.timeline[0]?.label).toBe('Shipped');
  });
});

describe('shouldOfferCancel / cancel payload', () => {
  it('prefers server can_cancel and never invents eligibility math', () => {
    expect(shouldOfferCancel({ status: 'shipped', canCancel: true })).toBe(true);
    expect(shouldOfferCancel({ status: 'paid', canCancel: false })).toBe(false);
    expect(shouldOfferCancel({ status: 'cancelled', canCancel: null })).toBe(false);
    expect(shouldOfferCancel({ status: 'paid', canCancel: null })).toBe(true);
  });

  it('builds optional cancel reason body', () => {
    expect(buildCancelOrderPayload()).toEqual({});
    expect(buildCancelOrderPayload('  Changed mind  ')).toEqual({
      reason: 'Changed mind',
    });
  });
});

describe('normalizeOrdersFilter', () => {
  it('defaults unknown filters to all', () => {
    expect(normalizeOrdersFilter('active')).toBe('active');
    expect(normalizeOrdersFilter('nope')).toBe('all');
  });
});
