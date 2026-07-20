import type { Delivery } from "@/lib/delivery/types";
import { DELIVERY_STATUS } from "@/lib/delivery/types";
import type { Order, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

/** Canonical logistics tracking statuses shown to customers. */
export const ORDER_TRACKING_STATUS = {
  ORDER_PLACED: "ORDER_PLACED",
  PAYMENT_CONFIRMED: "PAYMENT_CONFIRMED",
  PROCESSING: "PROCESSING",
  PACKED: "PACKED",
  SHIPPED: "SHIPPED",
  IN_TRANSIT: "IN_TRANSIT",
  DELIVERED: "DELIVERED",
} as const;

export type OrderTrackingStatus =
  (typeof ORDER_TRACKING_STATUS)[keyof typeof ORDER_TRACKING_STATUS];

export type StatusHistoryUpdatedBy = "system" | "admin";

export type OrderStatusHistoryEntry = {
  status: OrderTrackingStatus;
  timestamp: string;
  updatedBy: StatusHistoryUpdatedBy;
};

export type TrackingStepDefinition = {
  status: OrderTrackingStatus;
  label: string;
  description: string;
  icon: string;
};

export const TRACKING_STEPS: TrackingStepDefinition[] = [
  {
    status: ORDER_TRACKING_STATUS.ORDER_PLACED,
    label: "Order Placed",
    description: "Your order has been received.",
    icon: "📋",
  },
  {
    status: ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED,
    label: "Payment Confirmed",
    description: "Payment verified successfully.",
    icon: "💳",
  },
  {
    status: ORDER_TRACKING_STATUS.PROCESSING,
    label: "Purchasing",
    description: "Your supplier is preparing your order.",
    icon: "⚙️",
  },
  {
    status: ORDER_TRACKING_STATUS.PACKED,
    label: "Packed",
    description: "Your order is packed and ready to ship.",
    icon: "📦",
  },
  {
    status: ORDER_TRACKING_STATUS.SHIPPED,
    label: "Shipped",
    description: "Your order is on its way from China.",
    icon: "🚚",
  },
  {
    status: ORDER_TRACKING_STATUS.IN_TRANSIT,
    label: "Arrived Tanzania",
    description: "Your shipment has arrived in Tanzania.",
    icon: "✈️",
  },
  {
    status: ORDER_TRACKING_STATUS.DELIVERED,
    label: "Delivered",
    description: "Delivered to your address. Enjoy!",
    icon: "✅",
  },
];

export type TrackingStepState = "completed" | "current" | "upcoming" | "cancelled";

export type TrackingTimelineStep = TrackingStepDefinition & {
  state: TrackingStepState;
  timestamp: string | null;
};

const STATUS_INDEX: Record<OrderTrackingStatus, number> = {
  [ORDER_TRACKING_STATUS.ORDER_PLACED]: 0,
  [ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED]: 1,
  [ORDER_TRACKING_STATUS.PROCESSING]: 2,
  [ORDER_TRACKING_STATUS.PACKED]: 3,
  [ORDER_TRACKING_STATUS.SHIPPED]: 4,
  [ORDER_TRACKING_STATUS.IN_TRANSIT]: 5,
  [ORDER_TRACKING_STATUS.DELIVERED]: 6,
};

export function getTrackingStatusIndex(status: OrderTrackingStatus): number {
  return STATUS_INDEX[status];
}

export function mapOrderStatusToTrackingStatus(status: OrderStatus): OrderTrackingStatus | null {
  switch (status) {
    case ORDER_STATUS.PROCESSING:
      return ORDER_TRACKING_STATUS.PROCESSING;
    case ORDER_STATUS.PACKED:
      return ORDER_TRACKING_STATUS.PACKED;
    case ORDER_STATUS.SHIPPED:
      return ORDER_TRACKING_STATUS.SHIPPED;
    case ORDER_STATUS.IN_TRANSIT:
      return ORDER_TRACKING_STATUS.IN_TRANSIT;
    case ORDER_STATUS.DELIVERED:
      return ORDER_TRACKING_STATUS.DELIVERED;
    default:
      return null;
  }
}

/** Infer the furthest tracking milestone reached from order fields. */
export function resolveCurrentTrackingStatus(order: Order): OrderTrackingStatus {
  if (order.status === ORDER_STATUS.CANCELLED) {
    return ORDER_TRACKING_STATUS.ORDER_PLACED;
  }

  const fulfillmentStatus = mapOrderStatusToTrackingStatus(order.status);
  if (fulfillmentStatus) {
    return fulfillmentStatus;
  }

  if (
    order.paymentStatus === PAYMENT_STATUS.PAID ||
    order.status === ORDER_STATUS.CONFIRMED
  ) {
    return ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED;
  }

  return ORDER_TRACKING_STATUS.ORDER_PLACED;
}

export function getLatestHistoryStatus(
  history: OrderStatusHistoryEntry[],
): OrderTrackingStatus | null {
  if (history.length === 0) {
    return null;
  }
  return history[history.length - 1]?.status ?? null;
}

export function getHistoryTimestamp(
  history: OrderStatusHistoryEntry[],
  status: OrderTrackingStatus,
): string | null {
  const entries = history.filter((entry) => entry.status === status);
  if (entries.length === 0) {
    return null;
  }
  return entries[entries.length - 1]?.timestamp ?? null;
}

export function getDeliveryStageTimestamp(
  delivery: Delivery | null | undefined,
  status: OrderTrackingStatus,
): string | null {
  if (!delivery) {
    return null;
  }

  const deliveryStatuses = new Set<string>(Object.values(DELIVERY_STATUS));
  if (!deliveryStatuses.has(status)) {
    return null;
  }

  const entry = delivery.stageTimestamps.find((stage) => stage.status === status);
  return entry?.timestamp ?? null;
}

export function buildTrackingTimeline(
  order: Order,
  delivery?: Delivery | null,
): TrackingTimelineStep[] {
  const history = order.statusHistory ?? [];
  const isCancelled = order.status === ORDER_STATUS.CANCELLED;
  const currentStatus =
    getLatestHistoryStatus(history) ?? resolveCurrentTrackingStatus(order);
  const currentIndex = getTrackingStatusIndex(currentStatus);

  return TRACKING_STEPS.map((step, index) => {
    let state: TrackingStepState;
    if (isCancelled) {
      state = "cancelled";
    } else if (index < currentIndex) {
      state = "completed";
    } else if (index === currentIndex) {
      state = "current";
    } else {
      state = "upcoming";
    }

    const timestamp =
      getDeliveryStageTimestamp(delivery, step.status) ??
      getHistoryTimestamp(history, step.status) ??
      (step.status === ORDER_TRACKING_STATUS.ORDER_PLACED ? order.createdAt : null) ??
      (state === "current" || state === "completed" ? order.updatedAt : null);

    return {
      ...step,
      state,
      timestamp,
    };
  });
}

export function getTrackingHeadlineFromTimeline(timeline: TrackingTimelineStep[]): string {
  const current = timeline.find((step) => step.state === "current");
  if (current) {
    return current.description;
  }

  const delivered = timeline.find(
    (step) => step.status === ORDER_TRACKING_STATUS.DELIVERED && step.state === "completed",
  );
  if (delivered) {
    return delivered.description;
  }

  return "Tracking your order.";
}
