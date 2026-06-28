import type { OrderTrackingWsEvent } from "@/lib/order/order-tracking-ws-types";

type WsClient = {
  readyState: number;
  send: (data: string) => void;
  on: (event: "close" | "error", listener: () => void) => void;
};

type TrackingSubscription = {
  ws: WsClient;
  orderId: string;
};

declare global {
  var __chinaOrderTzTrackingWsClients: Set<TrackingSubscription> | undefined;
}

function getSubscriptions(): Set<TrackingSubscription> {
  if (!globalThis.__chinaOrderTzTrackingWsClients) {
    globalThis.__chinaOrderTzTrackingWsClients = new Set();
  }
  return globalThis.__chinaOrderTzTrackingWsClients;
}

export function broadcastOrderTrackingEvent(event: OrderTrackingWsEvent): void {
  const payload = JSON.stringify(event);
  const orderId = "orderId" in event ? event.orderId : null;

  for (const subscription of getSubscriptions()) {
    if (orderId && subscription.orderId !== orderId) {
      continue;
    }

    if (subscription.ws.readyState === 1) {
      subscription.ws.send(payload);
    }
  }
}

export function registerOrderTrackingWsClient(ws: WsClient, orderId: string): void {
  const subscriptions = getSubscriptions();
  const subscription: TrackingSubscription = { ws, orderId };
  subscriptions.add(subscription);

  ws.send(JSON.stringify({ type: "connected", orderId } satisfies OrderTrackingWsEvent));

  ws.on("close", () => {
    subscriptions.delete(subscription);
  });

  ws.on("error", () => {
    subscriptions.delete(subscription);
  });
}

export function getOrderTrackingWsClientCount(): number {
  return getSubscriptions().size;
}
