import type { Order, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";

export type TrackingStage = {
  id: string;
  status: OrderStatus;
  label: string;
  description: string;
  icon: string;
};

/** Customer-facing fulfillment lifecycle (6 steps). */
export const CUSTOMER_TRACKING_STAGES: TrackingStage[] = [
  {
    id: "pending",
    status: ORDER_STATUS.PENDING,
    label: "Pending",
    description: "Order received — awaiting processing.",
    icon: "📋",
  },
  {
    id: "processing",
    status: ORDER_STATUS.PROCESSING,
    label: "Processing",
    description: "We are preparing your items.",
    icon: "⚙️",
  },
  {
    id: "packed",
    status: ORDER_STATUS.PACKED,
    label: "Packed",
    description: "Your order is packed and ready to ship.",
    icon: "📦",
  },
  {
    id: "shipped",
    status: ORDER_STATUS.SHIPPED,
    label: "Shipped",
    description: "Your order has left our warehouse.",
    icon: "🚚",
  },
  {
    id: "in_transit",
    status: ORDER_STATUS.IN_TRANSIT,
    label: "In Transit",
    description: "On the way to your delivery address.",
    icon: "✈️",
  },
  {
    id: "delivered",
    status: ORDER_STATUS.DELIVERED,
    label: "Delivered",
    description: "Delivered to your address.",
    icon: "✅",
  },
];

export const ADMIN_TRACKABLE_STATUSES: OrderStatus[] = [
  ORDER_STATUS.PENDING,
  ORDER_STATUS.PROCESSING,
  ORDER_STATUS.PACKED,
  ORDER_STATUS.SHIPPED,
  ORDER_STATUS.IN_TRANSIT,
  ORDER_STATUS.DELIVERED,
];

export function getAdminDisplayStatus(status: OrderStatus): OrderStatus {
  if (
    status === ORDER_STATUS.CONFIRMED ||
    status === ORDER_STATUS.PENDING_PAYMENT ||
    status === ORDER_STATUS.PENDING
  ) {
    return ORDER_STATUS.PENDING;
  }

  if (ADMIN_TRACKABLE_STATUSES.includes(status)) {
    return status;
  }

  return ORDER_STATUS.PENDING;
}

function statusToStageIndex(status: OrderStatus): number {
  switch (status) {
    case ORDER_STATUS.PROCESSING:
      return 1;
    case ORDER_STATUS.PACKED:
      return 2;
    case ORDER_STATUS.SHIPPED:
      return 3;
    case ORDER_STATUS.IN_TRANSIT:
      return 4;
    case ORDER_STATUS.DELIVERED:
      return 5;
    case ORDER_STATUS.CANCELLED:
      return -1;
    case ORDER_STATUS.CONFIRMED:
    case ORDER_STATUS.PENDING_PAYMENT:
    case ORDER_STATUS.PENDING:
    default:
      return 0;
  }
}

export type TrackingStageState = "completed" | "current" | "upcoming" | "cancelled";

export function getTrackingStageStates(order: Order): TrackingStageState[] {
  if (order.status === ORDER_STATUS.CANCELLED) {
    return CUSTOMER_TRACKING_STAGES.map(() => "cancelled" as const);
  }

  const currentIndex = statusToStageIndex(order.status);

  return CUSTOMER_TRACKING_STAGES.map((_, index) => {
    if (index < currentIndex) return "completed";
    if (index === currentIndex) return "current";
    return "upcoming";
  });
}

export function getTrackingHeadline(order: Order): string {
  if (order.status === ORDER_STATUS.CANCELLED) {
    return "This order was cancelled.";
  }

  const stage = CUSTOMER_TRACKING_STAGES[statusToStageIndex(order.status)];
  return stage?.description ?? "Tracking your order.";
}

export function getTrackingStageLabel(status: OrderStatus): string {
  const index = statusToStageIndex(status);
  if (index < 0) {
    return "Cancelled";
  }
  return CUSTOMER_TRACKING_STAGES[index]?.label ?? "Pending";
}
