import type { NotificationWsEvent } from "@/lib/notifications/notification-ws-types";

type WsClient = {
  readyState: number;
  send: (data: string) => void;
  on: (event: "close" | "error", listener: () => void) => void;
};

type NotificationSubscription = {
  ws: WsClient;
  userId: string;
};

declare global {
  var __chinaOrderTzNotificationWsClients: Set<NotificationSubscription> | undefined;
}

function getSubscriptions(): Set<NotificationSubscription> {
  if (!globalThis.__chinaOrderTzNotificationWsClients) {
    globalThis.__chinaOrderTzNotificationWsClients = new Set();
  }
  return globalThis.__chinaOrderTzNotificationWsClients;
}

export function broadcastNotificationEvent(event: NotificationWsEvent): void {
  const payload = JSON.stringify(event);
  const userId = "userId" in event ? event.userId : null;

  for (const subscription of getSubscriptions()) {
    if (userId && subscription.userId !== userId) {
      continue;
    }

    if (subscription.ws.readyState === 1) {
      subscription.ws.send(payload);
    }
  }
}

export function registerNotificationWsClient(ws: WsClient, userId: string): void {
  const subscriptions = getSubscriptions();
  const subscription: NotificationSubscription = { ws, userId };
  subscriptions.add(subscription);

  ws.send(JSON.stringify({ type: "connected", userId } satisfies NotificationWsEvent));

  ws.on("close", () => {
    subscriptions.delete(subscription);
  });

  ws.on("error", () => {
    subscriptions.delete(subscription);
  });
}

export function getNotificationWsClientCount(): number {
  return getSubscriptions().size;
}
