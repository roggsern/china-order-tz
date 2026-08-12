import { apiClient } from '@/src/core/api';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

export function mapUnreadNotificationCount(raw: unknown): number {
  const envelope = asRecord(raw);
  const data = asRecord(envelope.data ?? raw);
  const count = data.unread_count;
  if (typeof count === 'number' && Number.isFinite(count) && count >= 0) {
    return Math.floor(count);
  }
  return 0;
}

export async function fetchUnreadNotificationCount(): Promise<number> {
  const response = await apiClient.get<unknown>('/notifications/unread-count');
  return mapUnreadNotificationCount(response);
}
