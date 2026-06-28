import type { Order } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";
import type { AdminOrdersWsHandlers } from "@/lib/admin/admin-orders-ws";
import { getAdminOrdersPollIntervalMs } from "@/lib/admin/realtime-config";

async function fetchServerOrders(): Promise<{ orders: Order[]; ok: boolean }> {
  try {
    const response = await fetch("/api/admin/orders", { cache: "no-store" });
    if (!response.ok) {
      return { orders: [], ok: false };
    }

    const payload = (await response.json()) as { orders?: Order[] };
    return { orders: (payload.orders ?? []).map((order) => normalizeOrder(order)), ok: true };
  } catch {
    return { orders: [], ok: false };
  }
}

function orderSignature(order: Order): string {
  return `${order.updatedAt}|${order.paymentStatus}|${order.status}`;
}

export function subscribeAdminOrdersPolling(handlers: AdminOrdersWsHandlers): () => void {
  if (typeof window === "undefined") {
    return () => {};
  }

  let disposed = false;
  let pollTimer: ReturnType<typeof setTimeout> | null = null;
  let knownOrders = new Map<string, string>();
  let seeded = false;
  let consecutiveFailures = 0;

  const clearPollTimer = () => {
    if (pollTimer) {
      clearTimeout(pollTimer);
      pollTimer = null;
    }
  };

  const scheduleNextPoll = () => {
    if (disposed) {
      return;
    }

    clearPollTimer();
    pollTimer = setTimeout(() => {
      pollTimer = null;
      void poll();
    }, getAdminOrdersPollIntervalMs(document.hidden));
  };

  const poll = async () => {
    if (disposed) {
      return;
    }

    const { orders, ok } = await fetchServerOrders();

    if (disposed) {
      return;
    }

    if (!ok) {
      consecutiveFailures += 1;
      if (consecutiveFailures >= 3) {
        handlers.onDisconnected();
      }
      scheduleNextPoll();
      return;
    }

    consecutiveFailures = 0;
    handlers.onConnected();

    const nextKnown = new Map<string, string>();

    for (const order of orders) {
      const signature = orderSignature(order);
      nextKnown.set(order.id, signature);

      if (!seeded) {
        continue;
      }

      const previousSignature = knownOrders.get(order.id);
      if (!previousSignature) {
        handlers.onOrderCreated(order);
        continue;
      }

      if (previousSignature !== signature) {
        handlers.onOrderUpdated(order);
      }
    }

    knownOrders = nextKnown;
    seeded = true;
    scheduleNextPoll();
  };

  const onVisibilityChange = () => {
    if (disposed) {
      return;
    }

    clearPollTimer();
    void poll();
  };

  document.addEventListener("visibilitychange", onVisibilityChange);
  void poll();

  return () => {
    disposed = true;
    clearPollTimer();
    document.removeEventListener("visibilitychange", onVisibilityChange);
    handlers.onDisconnected();
  };
}
