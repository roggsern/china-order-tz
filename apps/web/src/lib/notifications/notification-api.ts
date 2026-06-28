import type { Notification, NotificationsListResponse } from "@/lib/notifications/types";
import { normalizeUserId } from "@/lib/notifications/user-id";

export async function fetchNotifications(userId: string): Promise<NotificationsListResponse> {
  const response = await fetch(
    `/api/notifications?userId=${encodeURIComponent(normalizeUserId(userId))}`,
    { cache: "no-store" },
  );

  if (!response.ok) {
    throw new Error("Unable to load notifications.");
  }

  return (await response.json()) as NotificationsListResponse;
}

export async function markNotificationReadApi(
  userId: string,
  id: string,
): Promise<Notification> {
  const response = await fetch("/api/notifications/mark-read", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ userId: normalizeUserId(userId), id }),
  });

  if (!response.ok) {
    throw new Error("Unable to mark notification as read.");
  }

  const payload = (await response.json()) as { notification: Notification };
  return payload.notification;
}

export async function markAllNotificationsReadApi(
  userId: string,
): Promise<NotificationsListResponse & { marked: number }> {
  const response = await fetch("/api/notifications/mark-all-read", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ userId: normalizeUserId(userId) }),
  });

  if (!response.ok) {
    throw new Error("Unable to mark all notifications as read.");
  }

  return (await response.json()) as NotificationsListResponse & { marked: number };
}
