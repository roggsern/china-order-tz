import type { Delivery } from "@/lib/delivery/types";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import {
  ORDER_TRACKING_STATUS,
  buildTrackingTimeline,
  resolveCurrentTrackingStatus,
  type OrderTrackingStatus,
  type TrackingStepState,
} from "@/lib/order/tracking-status";

/**
 * Customer-facing import-commerce timeline (display only).
 * Maps onto existing tracking statuses without changing payment/shipping/order logic.
 */
export const CUSTOMER_TRACKING_DISPLAY_STATUS = {
  ORDER_PLACED: "ORDER_PLACED",
  PAYMENT_CONFIRMED: "PAYMENT_CONFIRMED",
  PURCHASING: "PURCHASING",
  PACKED: "PACKED",
  SHIPPED: "SHIPPED",
  ARRIVED_TANZANIA: "ARRIVED_TANZANIA",
  OUT_FOR_DELIVERY: "OUT_FOR_DELIVERY",
  DELIVERED: "DELIVERED",
} as const;

export type CustomerTrackingDisplayStatus =
  (typeof CUSTOMER_TRACKING_DISPLAY_STATUS)[keyof typeof CUSTOMER_TRACKING_DISPLAY_STATUS];

export type CustomerTrackingDisplayStep = {
  status: CustomerTrackingDisplayStatus;
  label: string;
  description: string;
  icon: string;
  state: TrackingStepState;
  timestamp: string | null;
  estimatedDuration: string;
  whatHappensNext: string;
};

const DISPLAY_STEPS: Array<{
  status: CustomerTrackingDisplayStatus;
  label: string;
  description: string;
  icon: string;
  estimatedDuration: string;
  whatHappensNext: string;
}> = [
  {
    status: CUSTOMER_TRACKING_DISPLAY_STATUS.ORDER_PLACED,
    label: "Order Placed",
    description: "We received your order successfully.",
    icon: "1",
    estimatedDuration: "Instant",
    whatHappensNext: "We're confirming your payment details.",
  },
  {
    status: CUSTOMER_TRACKING_DISPLAY_STATUS.PAYMENT_CONFIRMED,
    label: "Payment Confirmed",
    description: "Your payment has been verified.",
    icon: "2",
    estimatedDuration: "Usually within minutes",
    whatHappensNext: "We will place your purchase with the supplier.",
  },
  {
    status: CUSTOMER_TRACKING_DISPLAY_STATUS.PURCHASING,
    label: "Purchasing",
    description: "Your supplier is preparing your order.",
    icon: "3",
    estimatedDuration: "1–2 business days",
    whatHappensNext: "Once purchased, items are packed for international shipping.",
  },
  {
    status: CUSTOMER_TRACKING_DISPLAY_STATUS.PACKED,
    label: "Packed",
    description: "Your items are packed and ready to ship.",
    icon: "4",
    estimatedDuration: "Within 1 business day",
    whatHappensNext: "Your package will leave the warehouse for transit.",
  },
  {
    status: CUSTOMER_TRACKING_DISPLAY_STATUS.SHIPPED,
    label: "Shipped",
    description: "Your order is on its way from China.",
    icon: "5",
    estimatedDuration: "Depends on shipping method",
    whatHappensNext: "We'll update you when your shipment arrives in Tanzania.",
  },
  {
    status: CUSTOMER_TRACKING_DISPLAY_STATUS.ARRIVED_TANZANIA,
    label: "Arrived Tanzania",
    description: "Your shipment has arrived in Tanzania.",
    icon: "6",
    estimatedDuration: "1–3 business days for clearance",
    whatHappensNext: "Your package will move into local delivery soon.",
  },
  {
    status: CUSTOMER_TRACKING_DISPLAY_STATUS.OUT_FOR_DELIVERY,
    label: "Out for Delivery",
    description: "Your order is out for final delivery.",
    icon: "7",
    estimatedDuration: "Same day in most cities",
    whatHappensNext: "Please keep your phone nearby for delivery updates.",
  },
  {
    status: CUSTOMER_TRACKING_DISPLAY_STATUS.DELIVERED,
    label: "Delivered",
    description: "Delivered to your address. Enjoy!",
    icon: "8",
    estimatedDuration: "Complete",
    whatHappensNext: "Thank you for shopping with China Order TZ.",
  },
];

function mapTrackingStatusToDisplayIndex(
  trackingStatus: OrderTrackingStatus,
  hasAssignedDriver: boolean,
): number {
  switch (trackingStatus) {
    case ORDER_TRACKING_STATUS.ORDER_PLACED:
      return 0;
    case ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED:
      return 1;
    case ORDER_TRACKING_STATUS.PROCESSING:
      return 2;
    case ORDER_TRACKING_STATUS.PACKED:
      return 3;
    case ORDER_TRACKING_STATUS.SHIPPED:
      return 4;
    case ORDER_TRACKING_STATUS.IN_TRANSIT:
      return hasAssignedDriver ? 6 : 5;
    case ORDER_TRACKING_STATUS.DELIVERED:
      return 7;
    default:
      return 0;
  }
}

function resolveTimestamp(
  order: Order,
  delivery: Delivery | null | undefined,
  displayStatus: CustomerTrackingDisplayStatus,
  state: TrackingStepState,
): string | null {
  const baseTimeline = buildTrackingTimeline(order, delivery);
  const byCanonical: Partial<Record<CustomerTrackingDisplayStatus, OrderTrackingStatus>> = {
    ORDER_PLACED: ORDER_TRACKING_STATUS.ORDER_PLACED,
    PAYMENT_CONFIRMED: ORDER_TRACKING_STATUS.PAYMENT_CONFIRMED,
    PURCHASING: ORDER_TRACKING_STATUS.PROCESSING,
    PACKED: ORDER_TRACKING_STATUS.PACKED,
    SHIPPED: ORDER_TRACKING_STATUS.SHIPPED,
    ARRIVED_TANZANIA: ORDER_TRACKING_STATUS.IN_TRANSIT,
    OUT_FOR_DELIVERY: ORDER_TRACKING_STATUS.IN_TRANSIT,
    DELIVERED: ORDER_TRACKING_STATUS.DELIVERED,
  };

  const canonical = byCanonical[displayStatus];
  if (canonical) {
    const match = baseTimeline.find((step) => step.status === canonical);
    if (match?.timestamp) {
      return match.timestamp;
    }
  }

  if (state === "current" || state === "completed") {
    return order.updatedAt;
  }

  return null;
}

/** Build the 8-step customer logistics timeline for success/track UI. */
export function buildCustomerTrackingDisplayTimeline(
  order: Order,
  delivery?: Delivery | null,
): CustomerTrackingDisplayStep[] {
  const isCancelled = order.status === ORDER_STATUS.CANCELLED;
  const trackingStatus = resolveCurrentTrackingStatus(order);
  const currentIndex = mapTrackingStatusToDisplayIndex(
    trackingStatus,
    Boolean(delivery?.assignedDriver),
  );
  const allComplete = trackingStatus === ORDER_TRACKING_STATUS.DELIVERED;

  return DISPLAY_STEPS.map((step, index) => {
    let state: TrackingStepState;
    if (isCancelled) {
      state = "cancelled";
    } else if (allComplete || index < currentIndex) {
      state = "completed";
    } else if (index === currentIndex) {
      state = "current";
    } else {
      state = "upcoming";
    }

    return {
      ...step,
      state,
      timestamp: resolveTimestamp(order, delivery, step.status, state),
    };
  });
}

export function getCurrentDisplayStep(
  timeline: CustomerTrackingDisplayStep[],
): CustomerTrackingDisplayStep | null {
  return timeline.find((step) => step.state === "current") ?? null;
}

export function getTrackingWhatHappensNext(timeline: CustomerTrackingDisplayStep[]): {
  title: string;
  body: string;
  estimatedDuration: string;
} {
  const current = getCurrentDisplayStep(timeline);
  if (current) {
    return {
      title: current.label,
      body: current.whatHappensNext,
      estimatedDuration: current.estimatedDuration,
    };
  }

  const delivered = timeline.find(
    (step) =>
      step.status === CUSTOMER_TRACKING_DISPLAY_STATUS.DELIVERED &&
      step.state === "completed",
  );
  if (delivered) {
    return {
      title: "Delivered",
      body: delivered.whatHappensNext,
      estimatedDuration: delivered.estimatedDuration,
    };
  }

  return {
    title: "Tracking your order",
    body: "We'll notify you about every update along the way.",
    estimatedDuration: "—",
  };
}
