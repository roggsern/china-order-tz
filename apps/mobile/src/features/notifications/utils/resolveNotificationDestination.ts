import {
  buildOrderDetailHref,
  buildOrderTrackingHref,
} from '@/src/features/orders/utils/orderRoutes';
import {
  parseNotificationSemanticData,
  type NotificationSemanticData,
} from './notificationData';

export const NOTIFICATIONS_INBOX_HREF = '/(app)/account/notifications';
export const ACCOUNT_PROFILE_HREF = '/(app)/account/profile';
export const ACCOUNT_CHANGE_PASSWORD_HREF = '/(app)/account/change-password';

const TRACKING_EVENTS = new Set([
  'shipment_created',
  'shipment_arrived_tanzania',
  'tracking_updated',
  'shipment_status_updated',
]);

const ORDER_DETAIL_EVENTS = new Set([
  'order_created',
  'order_cancelled',
  'payment_confirmed',
  'order_delivered',
]);

const SUPPORT_EVENTS = new Set([
  'support_reply_received',
  'support_ticket_created',
  'support_ticket_assigned',
  'support_ticket_resolved',
]);

const SECURITY_PASSWORD_EVENTS = new Set(['password_changed']);
const SECURITY_PROFILE_EVENTS = new Set(['email_changed', 'email_verified']);

function isUuidLike(value: string): boolean {
  // Accept RFC 4122 / Laravel UUID v7 (version nibble may be 1–8).
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
    value,
  );
}

function buildSupportDetailHref(ticketId: string): string {
  return `/(app)/account/support/${encodeURIComponent(ticketId)}`;
}

/**
 * Map semantic notification data → existing native routes.
 * Never follows arbitrary URL / href fields from the payload.
 */
export function resolveNotificationDestination(rawData: unknown): string {
  const data = parseNotificationSemanticData(rawData);
  return resolveNotificationDestinationFromSemantic(data);
}

export function resolveNotificationDestinationFromSemantic(
  data: NotificationSemanticData,
): string {
  const event = (data.eventType ?? '').toLowerCase();

  if (TRACKING_EVENTS.has(event) && data.orderId && isUuidLike(data.orderId)) {
    return buildOrderTrackingHref(data.orderId);
  }

  if (ORDER_DETAIL_EVENTS.has(event) && data.orderId && isUuidLike(data.orderId)) {
    return buildOrderDetailHref(data.orderId);
  }

  if (SUPPORT_EVENTS.has(event) && data.ticketId && isUuidLike(data.ticketId)) {
    return buildSupportDetailHref(data.ticketId);
  }

  if (SECURITY_PASSWORD_EVENTS.has(event)) {
    return ACCOUNT_CHANGE_PASSWORD_HREF;
  }

  if (SECURITY_PROFILE_EVENTS.has(event)) {
    return ACCOUNT_PROFILE_HREF;
  }

  // Soft fallbacks when type unknown but canonical ids exist.
  if (data.orderId && isUuidLike(data.orderId)) {
    return buildOrderDetailHref(data.orderId);
  }
  if (data.ticketId && isUuidLike(data.ticketId)) {
    return buildSupportDetailHref(data.ticketId);
  }

  return NOTIFICATIONS_INBOX_HREF;
}
