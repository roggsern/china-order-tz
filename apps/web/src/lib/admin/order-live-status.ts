import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

export type AdminLiveStatus = "paid" | "processing" | "pending";

const PROCESSING_STATUSES = new Set<string>([
  ORDER_STATUS.PROCESSING,
  ORDER_STATUS.PACKED,
  ORDER_STATUS.SHIPPED,
  ORDER_STATUS.IN_TRANSIT,
]);

/** Lightweight fingerprint to skip re-renders when order snapshots are unchanged. */
export function computeOrdersFingerprint(orders: Order[]): string {
  if (orders.length === 0) {
    return "0";
  }

  return orders
    .map((order) => `${order.id}:${order.updatedAt}:${order.paymentStatus}:${order.status}`)
    .join("|");
}

/**
 * Admin live status for visual indicators:
 * - green = paid
 * - yellow = processing (fulfillment in progress)
 * - red = pending (awaiting payment or initial state)
 */
export function getAdminLiveStatus(order: Order): AdminLiveStatus {
  if (order.status === ORDER_STATUS.CANCELLED) {
    return "pending";
  }

  if (PROCESSING_STATUSES.has(order.status)) {
    return "processing";
  }

  if (order.paymentStatus === PAYMENT_STATUS.PAID) {
    return "paid";
  }

  return "pending";
}

export const ADMIN_LIVE_STATUS_STYLES: Record<
  AdminLiveStatus,
  { dot: string; ring: string; label: string }
> = {
  paid: {
    dot: "bg-emerald-500",
    ring: "ring-emerald-500/30",
    label: "Paid",
  },
  processing: {
    dot: "bg-amber-400",
    ring: "ring-amber-400/30",
    label: "Processing",
  },
  pending: {
    dot: "bg-red-500",
    ring: "ring-red-500/30",
    label: "Pending",
  },
};
