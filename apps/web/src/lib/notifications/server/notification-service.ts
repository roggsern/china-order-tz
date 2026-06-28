import type { CreateNotificationInput, Notification } from "@/lib/notifications/types";
import {
  appendStoredNotification,
  listStoredNotifications,
  markAllStoredNotificationsRead,
  markStoredNotificationRead,
} from "@/lib/notifications/server/notification-store";
import { broadcastNotificationEvent } from "@/lib/notifications/server/notification-broadcast";
import { normalizeUserId } from "@/lib/notifications/user-id";

function generateNotificationId(): string {
  return `ntf_${crypto.randomUUID().replace(/-/g, "")}`;
}

export async function createNotification(
  input: CreateNotificationInput,
): Promise<Notification | null> {
  const userId = normalizeUserId(input.userId);
  if (!userId) {
    return null;
  }

  const notification: Notification = {
    id: generateNotificationId(),
    userId,
    type: input.type,
    title: input.title,
    message: input.message,
    isRead: false,
    createdAt: new Date().toISOString(),
    dedupeKey: input.dedupeKey,
    orderId: input.orderId,
    href: input.href,
  };

  const saved = await appendStoredNotification(notification);
  if (!saved) {
    return null;
  }

  broadcastNotificationEvent({
    type: "notification_new",
    userId,
    notification: saved,
  });

  return saved;
}

export async function listNotifications(userId: string): Promise<{
  notifications: Notification[];
  unreadCount: number;
}> {
  const notifications = await listStoredNotifications(userId);
  const unreadCount = notifications.filter((entry) => !entry.isRead).length;
  return { notifications, unreadCount };
}

export async function markNotificationRead(
  userId: string,
  notificationId: string,
): Promise<Notification | null> {
  return markStoredNotificationRead(userId, notificationId);
}

export async function markAllNotificationsRead(userId: string): Promise<number> {
  return markAllStoredNotificationsRead(userId);
}
