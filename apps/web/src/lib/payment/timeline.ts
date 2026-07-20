import type { Order, OrderTimelineEvent } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

const TIMELINE_STAGES = [
  {
    id: "placed",
    title: "Order placed",
    description: "We received your order successfully.",
  },
  {
    id: "payment_received",
    title: "Payment received",
    description: "Payment has been confirmed.",
  },
  {
    id: "processing",
    title: "Processing",
    description: "We are preparing your items.",
  },
  {
    id: "packed",
    title: "Packed",
    description: "Your items are packed and ready to ship.",
  },
  {
    id: "shipped",
    title: "Shipped",
    description: "Your order is on its way.",
  },
  {
    id: "delivered",
    title: "Delivered",
    description: "Order delivered to your address.",
  },
] as const;

function resolveTimelineProgress(order: Order): {
  completedThrough: number;
  currentIndex: number | null;
} {
  if (order.status === ORDER_STATUS.CANCELLED) {
    if (order.paymentStatus === PAYMENT_STATUS.PAID) {
      return { completedThrough: 1, currentIndex: null };
    }
    return { completedThrough: 0, currentIndex: null };
  }

  if (order.status === ORDER_STATUS.DELIVERED) {
    return { completedThrough: 5, currentIndex: null };
  }

  if (order.status === ORDER_STATUS.IN_TRANSIT || order.status === ORDER_STATUS.SHIPPED) {
    return { completedThrough: 3, currentIndex: 4 };
  }

  if (order.status === ORDER_STATUS.PACKED) {
    return { completedThrough: 2, currentIndex: 3 };
  }

  if (order.status === ORDER_STATUS.PROCESSING) {
    return { completedThrough: 1, currentIndex: 2 };
  }

  if (order.status === ORDER_STATUS.CONFIRMED || order.paymentStatus === PAYMENT_STATUS.PAID) {
    return { completedThrough: 1, currentIndex: 2 };
  }

  if (
    order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT ||
    order.status === ORDER_STATUS.PENDING_PAYMENT ||
    order.status === ORDER_STATUS.PENDING ||
    order.paymentStatus === PAYMENT_STATUS.PENDING
  ) {
    return { completedThrough: 0, currentIndex: 1 };
  }

  return { completedThrough: 0, currentIndex: 1 };
}

export function syncTimelineWithOrder(order: Order): OrderTimelineEvent[] {
  const { completedThrough, currentIndex } = resolveTimelineProgress(order);

  return TIMELINE_STAGES.map((stage, index) => {
    let state: OrderTimelineEvent["state"];
    if (index <= completedThrough) {
      state = "completed";
    } else if (index === currentIndex) {
      state = "current";
    } else {
      state = "upcoming";
    }

    let timestamp: string | null = null;
    if (state === "completed" || state === "current") {
      if (index === 0) {
        timestamp = order.createdAt;
      } else if (index <= completedThrough || index === currentIndex) {
        timestamp = order.updatedAt;
      }
    }

    return {
      id: stage.id,
      title: stage.title,
      description: stage.description,
      timestamp,
      state,
    };
  });
}

export function buildInitialOrderTimeline(createdAt: string): OrderTimelineEvent[] {
  return syncTimelineWithOrder({
    id: "timeline-preview",
    orderNumber: "",
    paymentStatus: PAYMENT_STATUS.PENDING,
    paymentMethod: "mpesa",
    paymentReference: null,
    status: ORDER_STATUS.PENDING,
    createdAt,
    updatedAt: createdAt,
    customer: {
      firstName: "",
      lastName: "",
      email: "",
      phone: "",
    },
    shippingAddress: {
      addressLine1: "",
      addressLine2: "",
      city: "",
      region: "",
      postalCode: "",
      country: "Tanzania",
    },
    orderNotes: "",
    items: [],
    cartSnapshot: { items: [], savedForLater: [], discount: 0 },
    subtotal: 0,
    shippingTotal: 0,
    shippingMethod: null,
    itemShippingBreakdown: [],
    grandTotal: 0,
    totals: {
      productTotal: 0,
      originalProductTotal: 0,
      moqDiscount: 0,
      shippingTotal: 0,
      discount: 0,
      savings: 0,
      grandTotal: 0,
      itemCount: 0,
      uniqueItemCount: 0,
    },
    timeline: [],
  });
}
