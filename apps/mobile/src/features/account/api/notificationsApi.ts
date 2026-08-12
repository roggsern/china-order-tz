import { apiClient } from '@/src/core/api';

export type CustomerNotification = {
  id: string;
  type: string | null;
  title: string | null;
  message: string | null;
  isRead: boolean;
  readAt: string | null;
  createdAt: string | null;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function mapCustomerNotification(raw: unknown): CustomerNotification | null {
  const data = asRecord(raw);
  const id =
    typeof data.id === 'string' || typeof data.id === 'number'
      ? String(data.id)
      : '';
  if (!id) return null;
  return {
    id,
    type: stringField(data, 'type') ?? stringField(data, 'event_type'),
    title: stringField(data, 'title'),
    message: stringField(data, 'message'),
    isRead: data.is_read === true || Boolean(data.read_at),
    readAt: stringField(data, 'read_at'),
    createdAt: stringField(data, 'created_at'),
  };
}

export async function fetchNotifications(perPage = 20): Promise<{
  notifications: CustomerNotification[];
}> {
  const response = await apiClient.get<unknown>('/notifications', {
    per_page: perPage,
  });
  const rows = Array.isArray(response.data) ? response.data : [];
  return {
    notifications: rows
      .map(mapCustomerNotification)
      .filter((row): row is CustomerNotification => row !== null),
  };
}

export async function markNotificationRead(
  notificationId: string,
): Promise<CustomerNotification | null> {
  const response = await apiClient.patch<unknown>(
    `/notifications/${encodeURIComponent(notificationId)}/read`,
  );
  return mapCustomerNotification(response.data);
}

export async function markAllNotificationsRead(): Promise<number> {
  const response = await apiClient.post<{ marked?: number }>(
    '/notifications/read-all',
    {},
  );
  const data = asRecord(response.data);
  return typeof data.marked === 'number' ? data.marked : 0;
}
