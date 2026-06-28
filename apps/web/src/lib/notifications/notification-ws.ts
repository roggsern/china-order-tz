"use client";

import {
  NOTIFICATIONS_WS_PATH,
  parseNotificationWsEvent,
} from "@/lib/notifications/notification-ws-types";
import { normalizeUserId } from "@/lib/notifications/user-id";
import { resolveWebSocketUrl } from "@/lib/realtime/ws-config";

type NotificationWsHandlers = {
  onNotificationNew: () => void;
  onConnected?: () => void;
  onDisconnected?: () => void;
};

function getNotificationsWsUrl(userId: string): string | null {
  const normalized = normalizeUserId(userId);
  return resolveWebSocketUrl({
    explicitUrl: process.env.NEXT_PUBLIC_NOTIFICATIONS_WS_URL,
    path: NOTIFICATIONS_WS_PATH,
    searchParams: { userId: normalized },
  });
}

export function subscribeNotificationsWs(
  userId: string,
  handlers: NotificationWsHandlers,
): () => void {
  const url = getNotificationsWsUrl(userId);
  if (!url || typeof WebSocket === "undefined") {
    return () => {};
  }

  let disposed = false;
  let ws: WebSocket | null = null;
  let reconnectTimer: ReturnType<typeof setTimeout> | undefined;
  const normalizedUserId = normalizeUserId(userId);

  const connect = () => {
    if (disposed) {
      return;
    }

    ws = new WebSocket(url);

    ws.onopen = () => {
      handlers.onConnected?.();
    };

    ws.onmessage = (event) => {
      const message = parseNotificationWsEvent(String(event.data));
      if (!message) {
        return;
      }

      if (
        message.type === "notification_new" &&
        message.userId === normalizedUserId
      ) {
        handlers.onNotificationNew();
      }
    };

    ws.onclose = () => {
      handlers.onDisconnected?.();
      if (!disposed) {
        reconnectTimer = setTimeout(connect, 4000);
      }
    };

    ws.onerror = () => {
      ws?.close();
    };
  };

  connect();

  return () => {
    disposed = true;
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
    }
    ws?.close();
  };
}
