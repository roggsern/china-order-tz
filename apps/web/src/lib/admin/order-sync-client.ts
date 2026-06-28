import type { Order } from "@/lib/types/order";
import { ADMIN_ORDERS_SYNC_DEBOUNCE_MS } from "@/lib/admin/constants";

const pendingSyncTimers = new Map<string, ReturnType<typeof setTimeout>>();

export function queueOrderServerSync(order: Order): void {
  if (typeof window === "undefined") {
    return;
  }

  const existing = pendingSyncTimers.get(order.id);
  if (existing) {
    clearTimeout(existing);
  }

  pendingSyncTimers.set(
    order.id,
    setTimeout(() => {
      pendingSyncTimers.delete(order.id);
      void syncOrderToServer(order);
    }, ADMIN_ORDERS_SYNC_DEBOUNCE_MS),
  );
}

export async function syncOrderToServer(order: Order): Promise<void> {
  if (typeof window === "undefined") {
    return;
  }

  try {
    await fetch("/api/admin/orders", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ order }),
      keepalive: true,
    });
  } catch {
    // Non-blocking — local order storage remains source of truth for checkout.
  }
}
