import { buildCreateReturnPayload, mapCustomerReturnRequest } from './mapReturns';

describe('mapCustomerReturnRequest', () => {
  it('maps backend return state without inventing a refund', () => {
    const mapped = mapCustomerReturnRequest({
      id: 'ret-1',
      order_id: 'ord-1',
      status: 'requested',
      reason: 'Damaged on arrival',
      description: null,
      customer_notes: 'Leave at office',
      order: {
        id: 'ord-1',
        order_number: 'COTZ-1',
        status: 'delivered',
      },
      items: [
        {
          id: 'ri-1',
          order_item_id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
          quantity: 1,
          refund_amount: '15000.00',
          order_item: { product_name: 'Gown', quantity: 2 },
        },
      ],
      refunds: [],
    });

    expect(mapped).toMatchObject({
      id: 'ret-1',
      status: 'requested',
      reason: 'Damaged on arrival',
      items: [
        {
          orderItemId: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
          quantity: 1,
        },
      ],
      refunds: [],
    });
    expect(mapped?.orderStatus).toBe('delivered');
  });

  it('maps refund rows only when the backend includes them', () => {
    const mapped = mapCustomerReturnRequest({
      id: 'ret-2',
      status: 'approved',
      refunds: [
        {
          id: 'rf-1',
          amount: '15000.00',
          currency: 'TZS',
          status: 'pending',
          status_label: 'Pending',
        },
      ],
    });
    expect(mapped?.refunds[0]).toMatchObject({
      id: 'rf-1',
      status: 'pending',
      amount: '15000.00',
    });
  });
});

describe('buildCreateReturnPayload', () => {
  it('posts order_item_id and quantity using the backend contract', () => {
    expect(
      buildCreateReturnPayload({
        orderId: 'ord-1',
        reason: 'Wrong item received',
        description: 'Box opened',
        customerNotes: null,
        items: [
          {
            orderItemId: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            quantity: 2,
          },
        ],
      }),
    ).toEqual({
      reason: 'Wrong item received',
      description: 'Box opened',
      customer_notes: null,
      items: [
        {
          order_item_id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
          quantity: 2,
        },
      ],
    });
  });
});
