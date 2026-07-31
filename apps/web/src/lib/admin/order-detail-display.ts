import type { Order, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import type { ShippingAddress } from "@/lib/types/checkout";
import { PAYMENT_STATUS } from "@/lib/types/payment";

export const ADMIN_PAYMENT_STATUS_STEPS = [
  { id: "pending_payment", label: "Pending Payment" },
  { id: "paid", label: "Paid" },
  { id: "failed", label: "Failed" },
  { id: "refunded", label: "Refunded" },
] as const;

export const ADMIN_FULFILMENT_STATUS_STEPS = [
  { id: "pending", label: "Pending" },
  { id: "processing", label: "Processing" },
  { id: "shipped", label: "Shipped" },
  { id: "delivered", label: "Delivered" },
] as const;

export type AdminPaymentStatusStepId = (typeof ADMIN_PAYMENT_STATUS_STEPS)[number]["id"];
export type AdminFulfilmentStatusStepId = (typeof ADMIN_FULFILMENT_STATUS_STEPS)[number]["id"];

export type StatusTrackState = "completed" | "current" | "upcoming" | "cancelled";

export function isShippingAddressEmpty(address: ShippingAddress): boolean {
  const fields = [
    address.addressLine1,
    address.addressLine2,
    address.city,
    address.region,
    address.postalCode,
  ];

  return fields.every((value) => !value?.trim());
}

export function resolveAdminPaymentStatusStep(order: Order): AdminPaymentStatusStepId {
  if (order.paymentStatus === PAYMENT_STATUS.FAILED) {
    return "failed";
  }
  if (order.paymentStatus === PAYMENT_STATUS.REFUNDED) {
    return "refunded";
  }
  if (order.paymentStatus === PAYMENT_STATUS.PAID) {
    return "paid";
  }
  return "pending_payment";
}

export function resolveAdminPaymentStatusLabel(order: Order): string {
  const step = resolveAdminPaymentStatusStep(order);
  return ADMIN_PAYMENT_STATUS_STEPS.find((entry) => entry.id === step)?.label ?? "Pending Payment";
}

export function resolveAdminFulfilmentStatusStep(order: Order): AdminFulfilmentStatusStepId {
  if (order.status === ORDER_STATUS.CANCELLED) {
    return "pending";
  }
  if (order.status === ORDER_STATUS.DELIVERED) {
    return "delivered";
  }
  if (
    order.status === ORDER_STATUS.SHIPPED ||
    order.status === ORDER_STATUS.IN_TRANSIT
  ) {
    return "shipped";
  }
  if (order.status === ORDER_STATUS.PROCESSING || order.status === ORDER_STATUS.PACKED) {
    return "processing";
  }
  return "pending";
}

export function resolveAdminFulfilmentStatusLabel(order: Order): string {
  if (order.status === ORDER_STATUS.CANCELLED) {
    return "Cancelled";
  }

  const step = resolveAdminFulfilmentStatusStep(order);
  return ADMIN_FULFILMENT_STATUS_STEPS.find((entry) => entry.id === step)?.label ?? "Pending";
}

export function resolveStatusTrackStates(
  steps: ReadonlyArray<{ id: string }>,
  activeStepId: string,
  cancelled = false,
): StatusTrackState[] {
  if (cancelled) {
    return steps.map(() => "cancelled");
  }

  const activeIndex = steps.findIndex((step) => step.id === activeStepId);
  if (activeIndex < 0) {
    return steps.map(() => "upcoming");
  }

  return steps.map((_, index) => {
    if (index < activeIndex) {
      return "completed";
    }
    if (index === activeIndex) {
      return "current";
    }
    return "upcoming";
  });
}

export function resolveFulfilmentProgressIndex(order: Order): number {
  const step = resolveAdminFulfilmentStatusStep(order);
  return ADMIN_FULFILMENT_STATUS_STEPS.findIndex((entry) => entry.id === step);
}

export function mapOrderStatusToFulfilmentStep(status: OrderStatus): AdminFulfilmentStatusStepId {
  if (status === ORDER_STATUS.DELIVERED) {
    return "delivered";
  }
  if (status === ORDER_STATUS.SHIPPED || status === ORDER_STATUS.IN_TRANSIT) {
    return "shipped";
  }
  if (status === ORDER_STATUS.PROCESSING || status === ORDER_STATUS.PACKED) {
    return "processing";
  }
  return "pending";
}

export const ADMIN_SHIPPING_CONFIGURATION_MESSAGE = "Managed by shipping configuration";
