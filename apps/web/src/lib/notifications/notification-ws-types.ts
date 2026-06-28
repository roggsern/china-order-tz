import type { Notification } from "@/lib/notifications/types";

export const NOTIFICATIONS_WS_PATH = "/ws/notifications";

export type NotificationWsEvent =
  | { type: "connected"; userId: string }
  | {
      type: "notification_new";
      userId: string;
      notification: Notification;
    };

export function parseNotificationWsEvent(raw: string): NotificationWsEvent | null {
  try {
    const parsed = JSON.parse(raw) as NotificationWsEvent;
    if (!parsed || typeof parsed !== "object" || !("type" in parsed)) {
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}
