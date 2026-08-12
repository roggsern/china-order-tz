import {
  NOTIFICATIONS_INBOX_HREF,
  ACCOUNT_CHANGE_PASSWORD_HREF,
  ACCOUNT_PROFILE_HREF,
  resolveNotificationDestination,
} from '../utils/resolveNotificationDestination';
import { parseNotificationSemanticData } from '../utils/notificationData';

const ORDER_ID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const TICKET_ID = 'ffffffff-1111-4222-8333-444444444444';

describe('resolveNotificationDestination', () => {
  it('maps known order events to order detail', () => {
    for (const event of ['order_created', 'order_cancelled', 'payment_confirmed']) {
      expect(
        resolveNotificationDestination({
          event_type: event,
          order_id: ORDER_ID,
          order_number: 'COT-9',
        }),
      ).toBe(`/(app)/orders/${ORDER_ID}`);
    }
  });

  it('maps shipment events to tracking route', () => {
    for (const event of [
      'shipment_created',
      'shipment_arrived_tanzania',
      'order_delivered',
    ]) {
      expect(
        resolveNotificationDestination({
          event_type: event,
          order_id: ORDER_ID,
          shipment_id: 'ship-1',
        }),
      ).toBe(`/(app)/orders/${ORDER_ID}/tracking`);
    }
  });

  it('maps support reply to support detail', () => {
    expect(
      resolveNotificationDestination({
        event_type: 'support_reply_received',
        ticket_id: TICKET_ID,
        ticket_number: 'T-1',
      }),
    ).toBe(`/(app)/account/support/${TICKET_ID}`);
  });

  it('maps security events to account destinations', () => {
    expect(
      resolveNotificationDestination({ event_type: 'password_changed' }),
    ).toBe(ACCOUNT_CHANGE_PASSWORD_HREF);
    expect(
      resolveNotificationDestination({ event_type: 'email_changed' }),
    ).toBe(ACCOUNT_PROFILE_HREF);
  });

  it('falls back to inbox for unknown events', () => {
    expect(
      resolveNotificationDestination({ event_type: 'marketing_blast' }),
    ).toBe(NOTIFICATIONS_INBOX_HREF);
  });

  it('falls back safely for malformed ids', () => {
    expect(
      resolveNotificationDestination({
        event_type: 'order_created',
        order_id: 'not-a-uuid',
      }),
    ).toBe(NOTIFICATIONS_INBOX_HREF);
  });

  it('ignores arbitrary url field for navigation', () => {
    expect(
      resolveNotificationDestination({
        event_type: 'unknown',
        url: 'https://evil.example/path',
        href: '/(app)/orders/hack',
        path: '/stolen',
      }),
    ).toBe(NOTIFICATIONS_INBOX_HREF);
  });

  it('prefers order_id over order_number alone', () => {
    const data = parseNotificationSemanticData({
      event_type: 'order_created',
      order_id: ORDER_ID,
      order_number: 'COT-ONLY',
    });
    expect(data.orderId).toBe(ORDER_ID);
    expect(
      resolveNotificationDestination({
        event_type: 'order_created',
        order_number: 'COT-ONLY',
      }),
    ).toBe(NOTIFICATIONS_INBOX_HREF);
  });
});
