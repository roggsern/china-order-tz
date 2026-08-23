import {
  NOTIFICATIONS_INBOX_HREF,
  ACCOUNT_CHANGE_PASSWORD_HREF,
  ACCOUNT_PROFILE_HREF,
  resolveNotificationDestination,
} from '../utils/resolveNotificationDestination';
import { parseNotificationSemanticData } from '../utils/notificationData';

const ORDER_ID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const TICKET_ID = 'ffffffff-1111-4222-8333-444444444444';
/** Exact owner-QA order_id (Laravel UUID v7). */
const QA_ORDER_ID = '019fee4a-f110-7072-9f86-9fb15923793a';

describe('resolveNotificationDestination', () => {
  it('maps known order events to order detail', () => {
    for (const event of [
      'order_created',
      'order_cancelled',
      'payment_confirmed',
      'order_delivered',
    ]) {
      expect(
        resolveNotificationDestination({
          event_type: event,
          order_id: ORDER_ID,
          order_number: 'COT-9',
        }),
      ).toBe(`/(app)/orders/${ORDER_ID}`);
    }
  });

  it('maps owner-QA OrderCreated UUID v7 payload to order detail', () => {
    expect(
      resolveNotificationDestination({
        notification_id: 'n-qa',
        event_type: 'order_created',
        customer_name: 'QA Customer',
        order_number: 'COTZ-20260811-000001',
        order_id: QA_ORDER_ID,
      }),
    ).toBe(`/(app)/orders/${QA_ORDER_ID}`);
  });

  it('maps return events to return detail when return_id is present', () => {
    const returnId = 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff';
    for (const event of ['return_requested', 'return_approved', 'return_rejected']) {
      expect(
        resolveNotificationDestination({
          event_type: event,
          order_id: ORDER_ID,
          return_id: returnId,
        }),
      ).toBe(`/(app)/account/returns/${returnId}`);
    }
  });

  it('maps refund updates to order detail without inventing payment state', () => {
    for (const event of [
      'refund_requested',
      'refund_approved',
      'refund_completed',
      'refund_failed',
    ]) {
      expect(
        resolveNotificationDestination({
          event_type: event,
          order_id: ORDER_ID,
          refund_id: 'cccccccc-dddd-4eee-8fff-000000000000',
        }),
      ).toBe(`/(app)/orders/${ORDER_ID}`);
    }
  });

  it('maps post-pay receiving handoff events to order detail', () => {
    for (const event of [
      'warehouse_ready_for_pickup',
      'company_handover_pickup_requested',
      'company_handover_delivery_requested',
    ]) {
      expect(
        resolveNotificationDestination({
          event_type: event,
          order_id: ORDER_ID,
        }),
      ).toBe(`/(app)/orders/${ORDER_ID}`);
    }
  });

  it('maps shipment events to tracking route', () => {
    for (const event of [
      'shipment_created',
      'shipment_arrived_tanzania',
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

  it('maps owner-QA OrderCreated UUID v7 order_id to order detail', () => {
    const qaOrderId = '019fee4a-f110-7072-9f86-9fb15923793a';
    expect(
      resolveNotificationDestination({
        event_type: 'order_created',
        notification_id: 'notif-1',
        order_id: qaOrderId,
        order_number: 'COTZ-20260811-000001',
        customer_name: 'QA Customer',
      }),
    ).toBe(`/(app)/orders/${qaOrderId}`);
  });

  it('still ignores arbitrary url even with UUID v7 order_id when event unknown', () => {
    const qaOrderId = '019fee4a-f110-7072-9f86-9fb15923793a';
    // Soft order_id fallback remains for known semantic ids; url must not win.
    expect(
      resolveNotificationDestination({
        event_type: 'order_created',
        order_id: qaOrderId,
        url: 'https://evil.example/orders/hack',
      }),
    ).toBe(`/(app)/orders/${qaOrderId}`);
  });
});
