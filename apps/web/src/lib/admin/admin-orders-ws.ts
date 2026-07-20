import type { Order } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";
import { paymentService } from "@/lib/payment/PaymentService";
import { isAdminLocalOrderAuthorityEnabled } from "@/lib/config/env";
import {
  ADMIN_ORDERS_WS_INITIAL_RECONNECT_MS,
  ADMIN_ORDERS_WS_MAX_RECONNECT_MS,
} from "@/lib/admin/constants";
import {
  ADMIN_ORDERS_WS_PATH,
  parseAdminOrderWsEvent,
  type AdminOrderWsEvent,
} from "@/lib/admin/order-ws-types";
import { resolveWebSocketUrl } from "@/lib/realtime/ws-config";

export type AdminOrdersWsHandlers = {
  onConnected: () => void;
  onDisconnected: () => void;
  onOrderCreated: (order: Order) => void;
  onOrderUpdated: (order: Order) => void;
  onOrderBulkUpdated?: (orders: Order[]) => void;
  onOrderPatch: (orderId: string, patch: AdminOrderWsEvent & { type: "order_patch" }) => void;
  onAnalyticsUpdate?: () => void;
  onDeliveryUpdate?: (event: Extract<AdminOrderWsEvent, { type: "delivery_update" }>) => void;
};

export type AdminOrdersWsController = {
  disconnect: () => void;
};

const WS_OPEN = 1;
const WS_CONNECTING = 0;

function getAdminOrdersWsUrl(): string {
  if (typeof window === "undefined") {
    return "";
  }

  return (
    resolveWebSocketUrl({
      explicitUrl: process.env.NEXT_PUBLIC_ADMIN_WS_URL,
      path: ADMIN_ORDERS_WS_PATH,
    }) ?? ""
  );
}

function isSocketActive(ws: WebSocket | null): boolean {
  return ws?.readyState === WS_OPEN || ws?.readyState === WS_CONNECTING;
}

function createAdminOrdersWebSocket(handlers: AdminOrdersWsHandlers): AdminOrdersWsController {
  if (typeof window === "undefined") {
    return { disconnect: () => {} };
  }

  let ws: WebSocket | null = null;
  let disposed = false;
  let reconnectAttempt = 0;
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  let reportedConnected = false;

  const clearReconnectTimer = () => {
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
  };

  const reportConnected = () => {
    if (reportedConnected) {
      return;
    }
    reportedConnected = true;
    handlers.onConnected();
  };

  const reportDisconnected = () => {
    if (!reportedConnected) {
      return;
    }
    reportedConnected = false;
    handlers.onDisconnected();
  };

  const scheduleReconnect = () => {
    if (disposed || document.hidden || reconnectTimer) {
      return;
    }

    const delay = Math.min(
      ADMIN_ORDERS_WS_INITIAL_RECONNECT_MS * 2 ** reconnectAttempt,
      ADMIN_ORDERS_WS_MAX_RECONNECT_MS,
    );
    reconnectAttempt += 1;

    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      connect();
    }, delay);
  };

  const handleMessage = (event: MessageEvent<string>) => {
    const message = parseAdminOrderWsEvent(event.data);
    if (!message) {
      return;
    }

    switch (message.type) {
      case "connected":
        reconnectAttempt = 0;
        reportConnected();
        break;
      case "order_created":
        handlers.onOrderCreated(normalizeOrder(message.order));
        break;
      case "order_updated":
        handlers.onOrderUpdated(normalizeOrder(message.order));
        break;
      case "order_bulk_updated":
        for (const order of message.orders) {
          handlers.onOrderUpdated(normalizeOrder(order));
        }
        handlers.onOrderBulkUpdated?.(message.orders.map((order) => normalizeOrder(order)));
        break;
      case "payment_success":
        handlers.onOrderUpdated(normalizeOrder(message.order));
        break;
      case "order_patch":
        handlers.onOrderPatch(message.orderId, message);
        break;
      case "analytics_update":
        handlers.onAnalyticsUpdate?.();
        break;
      case "delivery_update":
        handlers.onDeliveryUpdate?.(message);
        break;
      default:
        break;
    }
  };

  const connect = () => {
    if (disposed || isSocketActive(ws)) {
      return;
    }

    const url = getAdminOrdersWsUrl();
    if (!url) {
      return;
    }

    clearReconnectTimer();

    const socket = new WebSocket(url);
    ws = socket;

    socket.onopen = () => {
      reconnectAttempt = 0;
    };

    socket.onmessage = handleMessage;

    socket.onerror = () => {
      // Close always follows error; avoid duplicate disconnect notifications.
    };

    socket.onclose = () => {
      if (ws === socket) {
        ws = null;
      }
      reportDisconnected();
      if (!disposed && !document.hidden) {
        scheduleReconnect();
      }
    };
  };

  const onVisibilityChange = () => {
    if (disposed) {
      return;
    }

    if (document.hidden) {
      clearReconnectTimer();
      if (ws) {
        ws.close();
        ws = null;
      }
      return;
    }

    reconnectAttempt = 0;
    clearReconnectTimer();
    if (!isSocketActive(ws)) {
      connect();
    }
  };

  document.addEventListener("visibilitychange", onVisibilityChange);
  connect();

  return {
    disconnect: () => {
      disposed = true;
      clearReconnectTimer();
      document.removeEventListener("visibilitychange", onVisibilityChange);
      if (ws) {
        ws.close();
        ws = null;
      }
      reportDisconnected();
    },
  };
}

const listeners = new Set<AdminOrdersWsHandlers>();
let sharedController: AdminOrdersWsController | null = null;
let subscriberCount = 0;

function dispatchToListeners(event: keyof AdminOrdersWsHandlers, ...args: unknown[]): void {
  for (const listener of listeners) {
    const handler = listener[event];
    if (typeof handler === "function") {
      (handler as (...handlerArgs: unknown[]) => void)(...args);
    }
  }
}

function ensureSharedController(): AdminOrdersWsController {
  if (sharedController) {
    return sharedController;
  }

  sharedController = createAdminOrdersWebSocket({
    onConnected: () => dispatchToListeners("onConnected"),
    onDisconnected: () => dispatchToListeners("onDisconnected"),
    onOrderCreated: (order) => dispatchToListeners("onOrderCreated", order),
    onOrderUpdated: (order) => dispatchToListeners("onOrderUpdated", order),
    onOrderPatch: (orderId, patch) => dispatchToListeners("onOrderPatch", orderId, patch),
    onAnalyticsUpdate: () => dispatchToListeners("onAnalyticsUpdate"),
    onDeliveryUpdate: (event) => dispatchToListeners("onDeliveryUpdate", event),
  });

  return sharedController;
}

/** Single shared WebSocket for the admin dashboard; safe across provider remounts. */
export function subscribeAdminOrdersWebSocket(handlers: AdminOrdersWsHandlers): () => void {
  if (typeof window === "undefined") {
    return () => {};
  }

  listeners.add(handlers);
  subscriberCount += 1;
  ensureSharedController();

  return () => {
    listeners.delete(handlers);
    subscriberCount = Math.max(0, subscriberCount - 1);

    if (subscriberCount === 0 && sharedController) {
      sharedController.disconnect();
      sharedController = null;
    }
  };
}

export function mergeOrderLists(serverOrders: Order[], localOrders: Order[]): Order[] {
  const merged = new Map<string, Order>();

  for (const order of localOrders) {
    merged.set(order.id, order);
  }

  for (const order of serverOrders) {
    const existing = merged.get(order.id);
    if (!existing || order.updatedAt >= existing.updatedAt) {
      merged.set(order.id, normalizeOrder(order));
    }
  }

  return [...merged.values()].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

export async function fetchInitialAdminOrders(): Promise<Order[]> {
  const localAuthority = isAdminLocalOrderAuthorityEnabled();
  const localOrders =
    localAuthority && typeof window !== "undefined" ? paymentService.listOrders() : [];

  try {
    const response = await fetch("/api/admin/orders", { cache: "no-store" });
    if (!response.ok) {
      return localAuthority ? localOrders : [];
    }

    const payload = (await response.json()) as {
      orders?: Order[];
      authority?: string;
    };
    const serverOrders = payload.orders ?? [];

    if (payload.authority === "laravel" || !localAuthority) {
      return serverOrders.map((order) => normalizeOrder(order));
    }

    return mergeOrderLists(serverOrders, localOrders);
  } catch {
    return localAuthority ? localOrders : [];
  }
}

export function upsertOrderInList(orders: Order[], order: Order): { next: Order[]; changed: boolean } {
  const index = orders.findIndex((entry) => entry.id === order.id);

  if (index === -1) {
    return {
      next: [order, ...orders].sort((a, b) => b.createdAt.localeCompare(a.createdAt)),
      changed: true,
    };
  }

  const current = orders[index];
  if (
    current.updatedAt === order.updatedAt &&
    current.paymentStatus === order.paymentStatus &&
    current.status === order.status
  ) {
    return { next: orders, changed: false };
  }

  const next = [...orders];
  next[index] = order;
  return { next, changed: true };
}

export function patchOrderInList(
  orders: Order[],
  orderId: string,
  patch: Partial<Order>,
): { next: Order[]; changed: boolean } {
  const index = orders.findIndex((entry) => entry.id === orderId);
  if (index === -1) {
    return { next: orders, changed: false };
  }

  const current = orders[index];
  const updated = normalizeOrder({
    ...current,
    ...patch,
    orderNumber: current.orderNumber,
  });

  if (
    updated.updatedAt === current.updatedAt &&
    updated.paymentStatus === current.paymentStatus &&
    updated.status === current.status
  ) {
    return { next: orders, changed: false };
  }

  const next = [...orders];
  next[index] = updated;
  return { next, changed: true };
}
