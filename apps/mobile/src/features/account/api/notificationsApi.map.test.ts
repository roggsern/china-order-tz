import { mapCustomerNotification } from './notificationsApi';

describe('mapCustomerNotification (Wave 6D data preservation)', () => {
  it('preserves semantic data from backend payload', () => {
    const mapped = mapCustomerNotification({
      id: 'n1',
      type: 'order_created',
      title: 'Order placed',
      message: 'Thanks',
      is_read: false,
      created_at: '2026-01-01T00:00:00Z',
      data: {
        event_type: 'order_created',
        order_id: '11111111-1111-4111-8111-111111111111',
        order_number: 'COT-1',
        url: 'https://evil.example/steal',
      },
    });

    expect(mapped).not.toBeNull();
    expect(mapped!.data.eventType).toBe('order_created');
    expect(mapped!.data.orderId).toBe('11111111-1111-4111-8111-111111111111');
    expect(mapped!.data.orderNumber).toBe('COT-1');
    expect(mapped!.rawData.url).toBe('https://evil.example/steal');
  });

  it('maps unread_count-style absence safely when fields missing', () => {
    const mapped = mapCustomerNotification({
      id: 42,
      title: 'Hello',
      data: {},
    });
    expect(mapped?.id).toBe('42');
    expect(mapped?.data.eventType).toBeNull();
    expect(mapped?.data.orderId).toBeNull();
  });
});
