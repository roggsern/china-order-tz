import type { Order, OrderTimelineEvent } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

const TIMELINE_STAGES = [
  {
    id: "created",
    title: "Order Created",
    description: "Your order has been created successfully.",
  },
  {
    id: "payment_pending",
    title: "Pending Payment",
    description: "Awaiting payment confirmation.",
  },
  {
    id: "payment_received",
    title: "Payment Received",
    description: "Payment has been confirmed.",
  },
  {
    id: "processing",
    title: "Processing",
    description: "We prepare your items for shipment.",
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
      return { completedThrough: 2, currentIndex: null };
    }
    return { completedThrough: 0, currentIndex: null };
  }

  if (order.status === ORDER_STATUS.DELIVERED) {
    return { completedThrough: 5, currentIndex: null };
  }
  if (order.status === ORDER_STATUS.SHIPPED) {
    return { completedThrough: 3, currentIndex: 4 };
  }
  if (order.status === ORDER_STATUS.PROCESSING) {
    return { completedThrough: 2, currentIndex: 3 };
  }
  if (order.status === ORDER_STATUS.CONFIRMED || order.paymentStatus === PAYMENT_STATUS.PAID) {
    return { completedThrough: 1, currentIndex: 2 };
  }

  if (
    order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT ||
    order.status === ORDER_STATUS.PENDING_PAYMENT
  ) {
    return { completedThrough: 0, currentIndex: 1 };
  }

  if (order.status === ORDER_STATUS.PENDING || order.paymentStatus === PAYMENT_STATUS.PENDING) {
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
      } else if (
        index === 1 &&
        (order.status === ORDER_STATUS.PENDING || order.status === ORDER_STATUS.PENDING_PAYMENT)
      ) {
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
      shippingTotal: 0,
      discount: 0,
      grandTotal: 0,
      itemCount: 0,
      uniqueItemCount: 0,
    },
    timeline: [],
  });
}
