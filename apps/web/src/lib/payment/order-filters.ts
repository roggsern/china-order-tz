import type { Order, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { getMethodByCode } from "@/lib/shipping/engine";

export type AdminOrderListFilter = "all" | "pending" | "paid" | "shipped" | "delivered";

export const ADMIN_ORDER_LIST_FILTERS: {
  id: AdminOrderListFilter;
  label: string;
  description: string;
}[] = [
  { id: "all", label: "All", description: "Every order" },
  { id: "pending", label: "Pending", description: "Awaiting payment" },
  { id: "paid", label: "Paid", description: "Payment received" },
  { id: "shipped", label: "Shipped", description: "In transit" },
  { id: "delivered", label: "Delivered", description: "Completed delivery" },
];

export type AdminOrderQueue =
  | "pending"
  | "paid"
  | "processing"
  | "shipping"
  | "completed"
  | "cancelled";

export const ADMIN_ORDER_QUEUES: {
  id: AdminOrderQueue;
  label: string;
  description: string;
}[] = [
  { id: "pending", label: "Pending", description: "Awaiting payment" },
  { id: "paid", label: "Paid", description: "Payment received" },
  { id: "processing", label: "Processing", description: "Being prepared" },
  { id: "shipping", label: "Shipping", description: "In transit" },
  { id: "completed", label: "Completed", description: "Delivered" },
  { id: "cancelled", label: "Cancelled", description: "Cancelled orders" },
];

export function getAdminOrderQueue(order: Order): AdminOrderQueue {
  if (order.status === ORDER_STATUS.CANCELLED) {
    return "cancelled";
  }
  if (order.status === ORDER_STATUS.DELIVERED) {
    return "completed";
  }
  if (order.status === ORDER_STATUS.SHIPPED || order.status === ORDER_STATUS.IN_TRANSIT) {
    return "shipping";
  }
  if (order.status === ORDER_STATUS.PROCESSING || order.status === ORDER_STATUS.PACKED) {
    return "processing";
  }
  if (order.paymentStatus === PAYMENT_STATUS.PAID || order.status === ORDER_STATUS.CONFIRMED) {
    return "paid";
  }
  if (
    order.paymentStatus === PAYMENT_STATUS.PENDING ||
    order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT ||
    order.status === ORDER_STATUS.PENDING ||
    order.status === ORDER_STATUS.PENDING_PAYMENT
  ) {
    return "pending";
  }
  return "pending";
}

export function filterOrdersByQueue(orders: Order[], queue: AdminOrderQueue): Order[] {
  return orders.filter((order) => getAdminOrderQueue(order) === queue);
}

export function countOrdersByQueue(orders: Order[]): Record<AdminOrderQueue, number> {
  return ADMIN_ORDER_QUEUES.reduce(
    (counts, { id }) => {
      counts[id] = filterOrdersByQueue(orders, id).length;
      return counts;
    },
    {} as Record<AdminOrderQueue, number>,
  );
}

export function filterOrdersByListFilter(
  orders: Order[],
  filter: AdminOrderListFilter,
): Order[] {
  if (filter === "all") {
    return orders;
  }

  if (filter === "pending") {
    return orders.filter(
      (order) =>
        order.paymentStatus === PAYMENT_STATUS.PENDING ||
        order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT,
    );
  }

  if (filter === "paid") {
    return orders.filter((order) => order.paymentStatus === PAYMENT_STATUS.PAID);
  }

  if (filter === "shipped") {
    return orders.filter(
      (order) =>
        order.status === ORDER_STATUS.SHIPPED || order.status === ORDER_STATUS.IN_TRANSIT,
    );
  }

  if (filter === "delivered") {
    return orders.filter((order) => order.status === ORDER_STATUS.DELIVERED);
  }

  return orders;
}

export function countOrdersByListFilter(
  orders: Order[],
): Record<AdminOrderListFilter, number> {
  return ADMIN_ORDER_LIST_FILTERS.reduce(
    (counts, { id }) => {
      counts[id] = filterOrdersByListFilter(orders, id).length;
      return counts;
    },
    {} as Record<AdminOrderListFilter, number>,
  );
}

export function getOrderFulfillmentLabel(status: OrderStatus): string {
  switch (status) {
    case ORDER_STATUS.PROCESSING:
      return "Processing";
    case ORDER_STATUS.PACKED:
      return "Packed";
    case ORDER_STATUS.SHIPPED:
      return "Shipped";
    case ORDER_STATUS.IN_TRANSIT:
      return "In Transit";
    case ORDER_STATUS.DELIVERED:
      return "Delivered";
    case ORDER_STATUS.CANCELLED:
      return "Cancelled";
    case ORDER_STATUS.CONFIRMED:
      return "Confirmed";
    case ORDER_STATUS.PENDING:
    case ORDER_STATUS.PENDING_PAYMENT:
    default:
      return "Pending";
  }
}

export function getOrderShippingMethodLabel(order: Order): string {
  if (order.shippingMethod) {
    return getMethodByCode(order.shippingMethod)?.name ?? order.shippingMethod;
  }

  const codes = [...new Set(order.items.map((item) => item.shippingMethod))];
  if (codes.length === 0) {
    return "—";
  }

  return codes
    .map((code) => getMethodByCode(code)?.name ?? code)
    .join(", ");
}

export function getShippingStatusLabel(status: OrderStatus): string {
  switch (status) {
    case ORDER_STATUS.PENDING:
    case ORDER_STATUS.PENDING_PAYMENT:
      return "Awaiting payment";
    case ORDER_STATUS.CONFIRMED:
      return "Payment confirmed — preparing shipment";
    case ORDER_STATUS.PROCESSING:
      return "Processing at warehouse";
    case ORDER_STATUS.PACKED:
      return "Packed and ready to ship";
    case ORDER_STATUS.SHIPPED:
      return "Shipped from warehouse";
    case ORDER_STATUS.IN_TRANSIT:
      return "In transit to your address";
    case ORDER_STATUS.DELIVERED:
      return "Delivered";
    case ORDER_STATUS.CANCELLED:
      return "Cancelled";
    default:
      return "Unknown";
  }
}
