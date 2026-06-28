import type { AdminOrdersWsHandlers } from "@/lib/admin/admin-orders-ws";
import { subscribeAdminOrdersPolling } from "@/lib/admin/admin-orders-polling";
import { subscribeAdminOrdersWebSocket } from "@/lib/admin/admin-orders-ws";
import {
  getAdminWsFallbackFailuresBeforePolling,
  resolveAdminRealtimeTransport,
  type AdminRealtimeTransport,
} from "@/lib/admin/realtime-config";

export type { AdminRealtimeTransport } from "@/lib/admin/realtime-config";

export type AdminOrdersRealtimeSubscription = {
  unsubscribe: () => void;
  transport: AdminRealtimeTransport;
};

export function getAdminRealtimeTransport(): AdminRealtimeTransport {
  return resolveAdminRealtimeTransport();
}

/** WebSocket in dev / external WS URL; polling on Vercel and production by default. */
export function subscribeAdminOrdersRealtime(
  handlers: AdminOrdersWsHandlers,
): AdminOrdersRealtimeSubscription {
  const preferred = resolveAdminRealtimeTransport();
  const state: { transport: AdminRealtimeTransport } = {
    transport: preferred === "polling" ? "polling" : "websocket",
  };

  if (preferred === "polling") {
    return {
      unsubscribe: subscribeAdminOrdersPolling(handlers),
      transport: "polling",
    };
  }

  let pollingUnsubscribe: (() => void) | null = null;
  let disconnectCount = 0;
  const maxFailures = getAdminWsFallbackFailuresBeforePolling();

  const wrappedHandlers: AdminOrdersWsHandlers = {
    onConnected: () => {
      disconnectCount = 0;
      handlers.onConnected();
    },
    onDisconnected: () => {
      disconnectCount += 1;

      if (disconnectCount >= maxFailures && !pollingUnsubscribe) {
        state.transport = "polling";
        pollingUnsubscribe = subscribeAdminOrdersPolling(handlers);
        return;
      }

      handlers.onDisconnected();
    },
    onOrderCreated: handlers.onOrderCreated,
    onOrderUpdated: handlers.onOrderUpdated,
    onOrderPatch: handlers.onOrderPatch,
    onAnalyticsUpdate: handlers.onAnalyticsUpdate,
    onDeliveryUpdate: handlers.onDeliveryUpdate,
  };

  const wsUnsubscribe = subscribeAdminOrdersWebSocket(wrappedHandlers);

  return {
    get transport() {
      return state.transport;
    },
    unsubscribe: () => {
      wsUnsubscribe();
      pollingUnsubscribe?.();
      pollingUnsubscribe = null;
    },
  };
}
