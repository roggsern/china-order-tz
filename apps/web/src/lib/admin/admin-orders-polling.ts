import type { Order } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";
import type { AdminOrdersWsHandlers } from "@/lib/admin/admin-orders-ws";
import {
  applyAdminOrdersPollResult,
  createAdminOrdersPollCycleState,
} from "@/lib/admin/admin-orders-poll-cycle";
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

export function subscribeAdminOrdersPolling(handlers: AdminOrdersWsHandlers): () => void {
  if (typeof window === "undefined") {
    return () => {};
  }

  let disposed = false;
  let pollTimer: ReturnType<typeof setTimeout> | null = null;
  let cycle = createAdminOrdersPollCycleState();

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

    const result = await fetchServerOrders();

    if (disposed) {
      return;
    }

    cycle = applyAdminOrdersPollResult(cycle, result, handlers);
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
