import type { Order, OrderTimelineEvent } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";

export const CUSTOMER_ORDER_PROGRESS_KEYS = {
  AWAITING_PAYMENT: "AWAITING_PAYMENT",
  ORDER_CONFIRMED: "ORDER_CONFIRMED",
  PREPARING: "PREPARING",
  READY_TO_SHIP: "READY_TO_SHIP",
  SHIPPED: "SHIPPED",
  ARRIVED_TANZANIA: "ARRIVED_TANZANIA",
  CHOOSE_RECEIVING_METHOD: "CHOOSE_RECEIVING_METHOD",
  DELIVERED: "DELIVERED",
  SENT_TO_AGENT: "SENT_TO_AGENT",
  DELIVERED_TO_AGENT: "DELIVERED_TO_AGENT",
  CANCELLED: "CANCELLED",
  REFUND_PENDING: "REFUND_PENDING",
  REFUNDED: "REFUNDED",
} as const;

export type CustomerOrderProgressKey =
  (typeof CUSTOMER_ORDER_PROGRESS_KEYS)[keyof typeof CUSTOMER_ORDER_PROGRESS_KEYS];

export type CustomerOrderProgressStep = {
  key: CustomerOrderProgressKey | string;
  label: string;
  completed: boolean;
};

export type CustomerOrderProgress = {
  current_key: CustomerOrderProgressKey | string;
  current_label: string;
  steps: CustomerOrderProgressStep[];
};

export type CustomerProgressDisplayStep = {
  key: string;
  label: string;
  description: string;
  icon: string;
  state: "completed" | "current" | "upcoming" | "cancelled";
  timestamp: string | null;
};

const STEP_DESCRIPTIONS: Record<string, string> = {
  ORDER_CONFIRMED: "Your order is confirmed and queued for fulfilment.",
  PREPARING: "We are preparing your items for shipping.",
  READY_TO_SHIP: "Your order is packed and ready to leave our warehouse.",
  SHIPPED: "Your order is on its way to you.",
  ARRIVED_TANZANIA: "Your order has arrived in Tanzania.",
  CHOOSE_RECEIVING_METHOD: "Choose how you would like to receive your order.",
  DELIVERED: "Your order has been delivered.",
  SENT_TO_AGENT: "Your order has been sent to your nominated agent.",
  DELIVERED_TO_AGENT: "Your order has been delivered to your nominated agent.",
  CANCELLED: "This order has been cancelled.",
  REFUND_PENDING: "Your refund is being processed.",
  REFUNDED: "Your refund has been completed.",
};

const AGENT_DELIVERY_DESCRIPTIONS: Record<string, string> = {
  ORDER_CONFIRMED: "Your order has been confirmed.",
  PREPARING: "We are preparing your items.",
  SENT_TO_AGENT: "Your order has been sent to your nominated agent.",
  DELIVERED_TO_AGENT: "Your order has been delivered to your nominated agent.",
};

const LOCAL_DELIVERY_DESCRIPTIONS: Record<string, string> = {
  ORDER_CONFIRMED: "Your order is confirmed and queued for preparation.",
  PREPARING: "We are preparing your order.",
  READY_TO_SHIP: "Your order is ready. We will notify you according to your collection preference.",
  DELIVERED: "Your order is complete. Thank you for shopping with us.",
};

const COMPANY_SHIPPING_DESCRIPTIONS: Record<string, string> = {
  ORDER_CONFIRMED: "Your order is confirmed and queued for fulfilment.",
  PREPARING: "We are preparing your items for shipping.",
  SHIPPED: "Your order is on its way to Tanzania.",
  ARRIVED_TANZANIA: "Your order has arrived in Tanzania.",
  CHOOSE_RECEIVING_METHOD: "Choose how you would like to receive your order.",
  DELIVERED: "Your order is complete. Thank you for shopping with us.",
};

const LOCAL_WHAT_HAPPENS_NEXT: Record<string, string> = {
  ORDER_CONFIRMED: "We will begin preparing your order shortly.",
  PREPARING: "We will notify you when your order is ready.",
  READY_TO_SHIP: "Please follow the instructions in your notification to collect or arrange delivery.",
  DELIVERED: "Thank you for shopping with China Order TZ.",
};

const TERMINAL_PROGRESS_KEYS = new Set<string>([
  CUSTOMER_ORDER_PROGRESS_KEYS.CANCELLED,
  CUSTOMER_ORDER_PROGRESS_KEYS.REFUND_PENDING,
  CUSTOMER_ORDER_PROGRESS_KEYS.REFUNDED,
]);

const STEP_WHAT_HAPPENS_NEXT: Record<string, string> = {
  AWAITING_PAYMENT: "Complete payment to confirm your order.",
  ORDER_CONFIRMED: "We will begin preparing your order shortly.",
  PREPARING: "We will notify you when your order is ready to ship.",
  READY_TO_SHIP: "Your order will ship soon.",
  SHIPPED: "We will notify you when delivery is complete.",
  DELIVERED: "Thank you for shopping with China Order TZ.",
  SENT_TO_AGENT: "We will notify you when your order reaches your nominated agent.",
  DELIVERED_TO_AGENT: "Thank you for shopping with China Order TZ.",
  CANCELLED: "No further action is required unless a refund is pending.",
  REFUND_PENDING: "We will notify you when your refund is completed.",
  REFUNDED: "Thank you for your patience.",
};

export function isAgentDeliveryProgress(progress: CustomerOrderProgress): boolean {
  return progress.steps.some((step) => step.key === CUSTOMER_ORDER_PROGRESS_KEYS.SENT_TO_AGENT);
}

export function isLocalDeliveryProgress(progress: CustomerOrderProgress): boolean {
  const keys = progress.steps.map((step) => step.key);

  return (
    keys.includes(CUSTOMER_ORDER_PROGRESS_KEYS.READY_TO_SHIP) &&
    !keys.includes(CUSTOMER_ORDER_PROGRESS_KEYS.SHIPPED) &&
    !keys.includes(CUSTOMER_ORDER_PROGRESS_KEYS.SENT_TO_AGENT)
  );
}

export function isCompanyShippingProgress(progress: CustomerOrderProgress): boolean {
  const keys = progress.steps.map((step) => step.key);

  return (
    keys.includes(CUSTOMER_ORDER_PROGRESS_KEYS.ARRIVED_TANZANIA) ||
    keys.includes(CUSTOMER_ORDER_PROGRESS_KEYS.CHOOSE_RECEIVING_METHOD)
  );
}

function descriptionForStep(
  step: CustomerOrderProgressStep,
  isAgentDelivery: boolean,
  isLocalDelivery: boolean,
  isCompanyShipping: boolean,
): string {
  if (isAgentDelivery && AGENT_DELIVERY_DESCRIPTIONS[step.key]) {
    return AGENT_DELIVERY_DESCRIPTIONS[step.key];
  }

  if (isLocalDelivery && LOCAL_DELIVERY_DESCRIPTIONS[step.key]) {
    return LOCAL_DELIVERY_DESCRIPTIONS[step.key];
  }

  if (isCompanyShipping && COMPANY_SHIPPING_DESCRIPTIONS[step.key]) {
    return COMPANY_SHIPPING_DESCRIPTIONS[step.key];
  }

  return STEP_DESCRIPTIONS[step.key] ?? step.label;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function parseCustomerOrderProgress(value: unknown): CustomerOrderProgress | null {
  if (!isRecord(value)) {
    return null;
  }

  const steps = Array.isArray(value.steps)
    ? value.steps
        .filter(isRecord)
        .map((step) => ({
          key: String(step.key ?? ""),
          label: String(step.label ?? ""),
          completed: Boolean(step.completed),
        }))
        .filter((step) => step.key.length > 0)
    : [];

  const currentKey = String(value.current_key ?? "");
  const currentLabel = String(value.current_label ?? "");

  if (!currentKey || !currentLabel || steps.length === 0) {
    return null;
  }

  return {
    current_key: currentKey,
    current_label: currentLabel,
    steps,
  };
}

export function resolveCustomerOrderProgress(
  order: Pick<Order, "status" | "progress">,
): CustomerOrderProgress | null {
  const parsed = parseCustomerOrderProgress(order.progress);
  if (parsed) {
    return parsed;
  }

  return null;
}

function resolveDisplayState(
  step: CustomerOrderProgressStep,
  currentKey: string,
  isCancelled: boolean,
): CustomerProgressDisplayStep["state"] {
  if (isCancelled) {
    return "cancelled";
  }

  if (step.key === currentKey) {
    return "current";
  }

  if (step.completed) {
    return "completed";
  }

  return "upcoming";
}

export function buildCustomerProgressDisplayTimeline(
  progress: CustomerOrderProgress,
  options?: {
    isCancelled?: boolean;
    timestamps?: Partial<Record<string, string | null>>;
  },
): CustomerProgressDisplayStep[] {
  const isCancelled = options?.isCancelled ?? false;
  const isAgentDelivery = isAgentDeliveryProgress(progress);
  const isLocalDelivery = isLocalDeliveryProgress(progress);
  const isCompanyShipping = isCompanyShippingProgress(progress);

  if (TERMINAL_PROGRESS_KEYS.has(progress.current_key)) {
    return progress.steps.map((step, index) => ({
      key: step.key,
      label: step.label,
      description: descriptionForStep(step, isAgentDelivery, isLocalDelivery, isCompanyShipping),
      icon: String(index + 1),
      state:
        progress.current_key === CUSTOMER_ORDER_PROGRESS_KEYS.CANCELLED
          ? ("cancelled" as const)
          : step.completed
            ? ("completed" as const)
            : ("current" as const),
      timestamp: options?.timestamps?.[step.key] ?? null,
    }));
  }

  if (progress.current_key === CUSTOMER_ORDER_PROGRESS_KEYS.AWAITING_PAYMENT) {
    return progress.steps.map((step, index) => ({
      key: step.key,
      label: step.label,
      description: descriptionForStep(step, isAgentDelivery, isLocalDelivery, isCompanyShipping),
      icon: String(index + 1),
      state: "upcoming" as const,
      timestamp: options?.timestamps?.[step.key] ?? null,
    }));
  }

  return progress.steps.map((step, index) => ({
    key: step.key,
    label: step.label,
    description: descriptionForStep(step, isAgentDelivery, isLocalDelivery, isCompanyShipping),
    icon: String(index + 1),
    state: resolveDisplayState(step, progress.current_key, isCancelled),
    timestamp: options?.timestamps?.[step.key] ?? null,
  }));
}

export function mapCustomerProgressToTimelineEvents(
  progress: CustomerOrderProgress,
  options?: {
    isCancelled?: boolean;
    timestamps?: Partial<Record<string, string | null>>;
  },
): OrderTimelineEvent[] {
  return buildCustomerProgressDisplayTimeline(progress, options).map((step) => ({
    id: step.key.toLowerCase(),
    title: step.label,
    description: step.description,
    timestamp: step.timestamp,
    state:
      step.state === "cancelled"
        ? "upcoming"
        : step.state,
  }));
}

export function getCustomerProgressWhatHappensNext(progress: CustomerOrderProgress): {
  title: string;
  body: string;
} {
  const isLocalDelivery = isLocalDeliveryProgress(progress);

  return {
    title: progress.current_label,
    body:
      (isLocalDelivery ? LOCAL_WHAT_HAPPENS_NEXT[progress.current_key] : undefined) ??
      STEP_WHAT_HAPPENS_NEXT[progress.current_key] ??
      "We will keep you updated as your order moves forward.",
  };
}

export function isOrderCancelledForProgress(order: Pick<Order, "status">): boolean {
  return order.status === ORDER_STATUS.CANCELLED;
}
