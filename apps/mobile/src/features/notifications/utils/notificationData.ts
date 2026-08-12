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
  };
}

export function extractNotificationContentData(raw: unknown): unknown {
  if (!raw || typeof raw !== 'object') return null;
  const record = raw as Record<string, unknown>;
  if ('data' in record) return record.data;
  return raw;
}
