import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import {
  ORDER_TRACKING_STATUS,
  mapOrderStatusToTrackingStatus,
  resolveCurrentTrackingStatus,
  type OrderStatusHistoryEntry,
  type OrderTrackingStatus,
  type StatusHistoryUpdatedBy,
} from "@/lib/order/tracking-status";

export function createInitialStatusHistory(
  createdAt: string,
  updatedBy: StatusHistoryUpdatedBy = "system",
): OrderStatusHistoryEntry[] {
  return [
    {
      status: ORDER_TRACKING_STATUS.ORDER_PLACED,
      timestamp: createdAt,
      updatedBy,
    },
  ];
}

export function appendStatusHistory(
  order: Order,
  status: OrderTrackingStatus,
  updatedBy: StatusHistoryUpdatedBy,
  timestamp: string = new Date().toISOString(),
): Order {
  const history = [...(order.statusHistory ?? [])];
  const last = history[history.length - 1];

  if (last?.status === status) {
    return order;
  }

  history.push({ status, timestamp, updatedBy });

  return {
    ...order,
    statusHistory: history,
    updatedAt: timestamp,
  };
}

/** Backfill status history for legacy orders missing the array. */
export function ensureStatusHistory(order: Order): OrderStatusHistoryEntry[] {
  if (Array.isArray(order.statusHistory) && order.statusHistory.length > 0) {
    return order.statusHistory;
  }

  const history: OrderStatusHistoryEntry[] = createInitialStatusHistory(order.createdAt);
  const current = resolveCurrentTrackingStatus(order);
  const sequence: OrderTrackingStatus[] = [
    ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED,
    ORDER_TRACKING_STATUS.PROCESSING,
    ORDER_TRACKING_STATUS.PACKED,
    ORDER_TRACKING_STATUS.SHIPPED,
    ORDER_TRACKING_STATUS.IN_TRANSIT,
    ORDER_TRACKING_STATUS.DELIVERED,
  ];

  for (const status of sequence) {
    if (getTrackingRank(status) <= getTrackingRank(current)) {
      history.push({
        status,
        timestamp: order.updatedAt,
        updatedBy: "system",
      });
    }
  }

  return dedupeHistory(history);
}

function getTrackingRank(status: OrderTrackingStatus): number {
  const ranks: OrderTrackingStatus[] = [
    ORDER_TRACKING_STATUS.ORDER_PLACED,
    ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED,
    ORDER_TRACKING_STATUS.PROCESSING,
    ORDER_TRACKING_STATUS.PACKED,
    ORDER_TRACKING_STATUS.SHIPPED,
    ORDER_TRACKING_STATUS.IN_TRANSIT,
    ORDER_TRACKING_STATUS.DELIVERED,
  ];
  return ranks.indexOf(status);
}

function dedupeHistory(history: OrderStatusHistoryEntry[]): OrderStatusHistoryEntry[] {
  const seen = new Set<OrderTrackingStatus>();
  return history.filter((entry) => {
    if (seen.has(entry.status)) {
      return false;
    }
    seen.add(entry.status);
    return true;
  });
}

export function applyOrderStatusHistoryPatch(
  order: Order,
  patch: Partial<Order>,
  updatedBy: StatusHistoryUpdatedBy = "system",
): Order {
  let next: Order = {
    ...order,
    ...patch,
    statusHistory: order.statusHistory ?? ensureStatusHistory(order),
  };

  if (
    patch.paymentStatus === PAYMENT_STATUS.PAID &&
    order.paymentStatus !== PAYMENT_STATUS.PAID
  ) {
    next = appendStatusHistory(next, ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED, updatedBy);
  }

  if (patch.status && patch.status !== order.status) {
    const trackingStatus = mapOrderStatusToTrackingStatus(patch.status);
    if (trackingStatus) {
      next = appendStatusHistory(next, trackingStatus, updatedBy);
    }
  }

  return next;
}

export function recordPaymentConfirmed(order: Order, updatedBy: StatusHistoryUpdatedBy = "system"): Order {
  let next = appendStatusHistory(order, ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED, updatedBy);
  if (order.status === ORDER_STATUS.PROCESSING) {
    next = appendStatusHistory(next, ORDER_TRACKING_STATUS.PROCESSING, updatedBy);
  }
  return next;
}
