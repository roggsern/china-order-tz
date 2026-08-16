export const ADMIN_DASHBOARD_HREF = '/(app)/(tabs)/dashboard';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function isUuidLike(value: string): boolean {
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
    value,
  );
}

/**
 * Map backend admin push destination + ids → Expo Router href.
 * Unknown destinations return null — callers fall back to dashboard.
 */
export function resolveAdminPushDestination(rawData: unknown): string | null {
  const data = asRecord(rawData);
  const destination = stringField(data, 'destination');
  if (!destination) return null;

  switch (destination) {
    case 'admin.dashboard':
      return ADMIN_DASHBOARD_HREF;
    case 'admin.orders':
      return '/(app)/(tabs)/orders';
    case 'admin.order_detail': {
      const orderId = stringField(data, 'order_id');
      if (!orderId || !isUuidLike(orderId)) return null;
      return `/(app)/(tabs)/orders/${encodeURIComponent(orderId)}`;
    }
    case 'admin.support':
      return '/(app)/(tabs)/support';
    case 'admin.support_ticket': {
      const ticketId = stringField(data, 'ticket_id');
      if (!ticketId || !isUuidLike(ticketId)) return null;
      return `/(app)/(tabs)/support/${encodeURIComponent(ticketId)}`;
    }
    case 'admin.low_stock':
      return '/(app)/(tabs)/more/low-stock';
    default:
      return null;
  }
}
