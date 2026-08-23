/**
 * Semantic notification payload helpers — never trust arbitrary URL fields.
 */

export type NotificationSemanticData = {
  eventType: string | null;
  notificationId: string | null;
  orderId: string | null;
  orderNumber: string | null;
  shipmentId: string | null;
  ticketId: string | null;
  ticketNumber: string | null;
  returnId: string | null;
  refundId: string | null;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : {};
}

function stringField(data: Record<string, unknown>, ...keys: string[]): string | null {
  for (const key of keys) {
    const value = data[key];
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim();
    }
    if (typeof value === 'number' && Number.isFinite(value)) {
      return String(value);
    }
  }
  return null;
}

/**
 * Normalize Expo / inbox notification `data` into semantic fields.
 * Ignores client-supplied `url` / `href` / `path` for navigation.
 */
export function parseNotificationSemanticData(
  raw: unknown,
): NotificationSemanticData {
  const data = asRecord(raw);
  return {
    eventType: stringField(data, 'event_type', 'type', 'eventType'),
    notificationId: stringField(data, 'notification_id', 'notificationId', 'id'),
    orderId: stringField(data, 'order_id', 'orderId'),
    orderNumber: stringField(data, 'order_number', 'orderNumber'),
    shipmentId: stringField(data, 'shipment_id', 'shipmentId'),
    ticketId: stringField(data, 'ticket_id', 'ticketId'),
    ticketNumber: stringField(data, 'ticket_number', 'ticketNumber'),
    returnId: stringField(data, 'return_id', 'returnId'),
    refundId: stringField(data, 'refund_id', 'refundId'),
  };
}

export function extractNotificationContentData(raw: unknown): unknown {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
  const record = raw as Record<string, unknown>;
  // Expo delivers semantic fields on content.data directly. Only unwrap a nested
  // `data` object when the outer payload lacks routing keys (avoid dropping event_type).
  if (
    'data' in record &&
    record.data &&
    typeof record.data === 'object' &&
    !Array.isArray(record.data) &&
    !('event_type' in record) &&
    !('order_id' in record) &&
    !('ticket_id' in record)
  ) {
    return record.data;
  }
  return raw;
}
