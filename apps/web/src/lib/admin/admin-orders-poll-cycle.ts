import type { Order } from "@/lib/types/order";

export type AdminOrdersPollHandlers = {
  onConnected: () => void;
  onDisconnected: () => void;
  onOrderCreated: (order: Order) => void;
  onOrderUpdated: (order: Order) => void;
  onInitialSnapshot?: (orders: Order[]) => void;
};

export type AdminOrdersPollCycleState = {
  seeded: boolean;
  consecutiveFailures: number;
  knownOrders: Map<string, string>;
};

export function createAdminOrdersPollCycleState(): AdminOrdersPollCycleState {
  return {
    seeded: false,
    consecutiveFailures: 0,
    knownOrders: new Map(),
  };
}

export function orderPollSignature(order: Order): string {
  return `${order.updatedAt}|${order.paymentStatus}|${order.status}`;
}

/**
 * One polling tick. The first successful payload hydrates via onInitialSnapshot
 * and does not emit create/update events (those would duplicate the snapshot).
 */
export function applyAdminOrdersPollResult(
  state: AdminOrdersPollCycleState,
  result: { ok: boolean; orders: Order[] },
  handlers: AdminOrdersPollHandlers,
): AdminOrdersPollCycleState {
  if (!result.ok) {
    const consecutiveFailures = state.consecutiveFailures + 1;
    if (consecutiveFailures >= 3) {
      handlers.onDisconnected();
    }

    return {
      ...state,
      consecutiveFailures,
    };
  }

  handlers.onConnected();

  const nextKnown = new Map<string, string>();
  for (const order of result.orders) {
    nextKnown.set(order.id, orderPollSignature(order));
  }

  if (!state.seeded) {
    handlers.onInitialSnapshot?.(result.orders);

    return {
      seeded: true,
      consecutiveFailures: 0,
      knownOrders: nextKnown,
    };
  }

  for (const order of result.orders) {
    const previousSignature = state.knownOrders.get(order.id);
    if (!previousSignature) {
      handlers.onOrderCreated(order);
      continue;
    }

    if (previousSignature !== orderPollSignature(order)) {
      handlers.onOrderUpdated(order);
    }
  }

  return {
    seeded: true,
    consecutiveFailures: 0,
    knownOrders: nextKnown,
  };
}
