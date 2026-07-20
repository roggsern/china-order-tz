import type { Notification, NotificationsListResponse } from "@/lib/notifications/types";
import { NOTIFICATION_TYPE } from "@/lib/notifications/types";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  fetchCustomerNotifications,
  fetchCustomerUnreadCount,
  markAllCustomerNotificationsRead,
  markCustomerNotificationRead,
  type CustomerNotification,
} from "@/lib/api/customer-notifications";
import { normalizeUserId } from "@/lib/notifications/user-id";

function mapEventToUiType(eventType: string | null | undefined): Notification["type"] {
  const value = (eventType ?? "").toLowerCase();
  if (value.includes("payment") || value.includes("otp") || value.includes("password")) {
    return NOTIFICATION_TYPE.PAYMENT;
  }
  if (
    value.includes("shipment") ||
    value.includes("tracking") ||
    value.includes("delivered") ||
    value.includes("warehouse")
  ) {
    return NOTIFICATION_TYPE.SHIPPING;
  }
  if (value.includes("order")) {
    return NOTIFICATION_TYPE.ORDER;
  }
  return NOTIFICATION_TYPE.SYSTEM;
}

function mapLaravelNotification(row: CustomerNotification, userId: string): Notification {
  const orderId =
    typeof row.data?.order_id === "string"
      ? row.data.order_id
      : typeof row.data?.order_number === "string"
        ? row.data.order_number
        : undefined;

  return {
    id: row.id,
    userId,
    type: mapEventToUiType(row.event_type ?? row.type),
    title: row.title,
    message: row.message,
    isRead: Boolean(row.is_read),
    createdAt: row.created_at ?? new Date().toISOString(),
    orderId,
    href: orderId ? `/account/orders/${encodeURIComponent(String(orderId))}` : "/account/notifications",
  };
}

/** Prefer Laravel enterprise inbox when Sanctum token exists. */
export async function fetchNotifications(userId: string): Promise<NotificationsListResponse> {
  const token = getCustomerApiToken();
  if (token) {
    const [{ notifications }, unreadCount] = await Promise.all([
      fetchCustomerNotifications({ perPage: 50 }),
      fetchCustomerUnreadCount(),
    ]);
    const mapped = notifications.map((row) =>
      mapLaravelNotification(row, normalizeUserId(userId)),
    );
    return { notifications: mapped, unreadCount };
  }

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
  const token = getCustomerApiToken();
  if (token) {
    const row = await markCustomerNotificationRead(id);
    return mapLaravelNotification(row, normalizeUserId(userId));
  }

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
  const token = getCustomerApiToken();
  if (token) {
    const marked = await markAllCustomerNotificationsRead();
    const refreshed = await fetchNotifications(userId);
    return { ...refreshed, marked };
  }

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
