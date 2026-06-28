"use client";

import {
  ORDER_TRACKING_WS_PATH,
  parseOrderTrackingWsEvent,
} from "@/lib/order/order-tracking-ws-types";
import { resolveWebSocketUrl } from "@/lib/realtime/ws-config";

type TrackingWsHandlers = {
  onStatusUpdated: () => void;
  onConnected?: () => void;
  onDisconnected?: () => void;
};

function getOrderTrackingWsUrl(orderId: string): string | null {
  return resolveWebSocketUrl({
    explicitUrl: process.env.NEXT_PUBLIC_ORDER_TRACKING_WS_URL,
    path: ORDER_TRACKING_WS_PATH,
    searchParams: { orderId },
  });
}

export function subscribeOrderTrackingWs(
  orderId: string,
  handlers: TrackingWsHandlers,
): () => void {
  const url = getOrderTrackingWsUrl(orderId);
  if (!url || typeof WebSocket === "undefined") {
    return () => {};
  }

  let disposed = false;
  let ws: WebSocket | null = null;
  let reconnectTimer: ReturnType<typeof setTimeout> | undefined;

  const connect = () => {
    if (disposed) {
      return;
    }

    ws = new WebSocket(url);

    ws.onopen = () => {
      handlers.onConnected?.();
    };

    ws.onmessage = (event) => {
      const message = parseOrderTrackingWsEvent(String(event.data));
      if (!message) {
        return;
      }

      if (message.type === "order_status_updated" && message.orderId === orderId) {
        handlers.onStatusUpdated();
      }

      if (message.type === "delivery_update" && message.orderId === orderId) {
        handlers.onStatusUpdated();
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
