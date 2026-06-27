import type { Order } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";

export const ORDERS_STORAGE_KEY = "china-order-tz-orders";
export const ORDERS_UPDATED_EVENT = "china-order-tz-orders-updated";

function readOrders(): Order[] {
  if (typeof window === "undefined") {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(ORDERS_STORAGE_KEY);
    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw) as Order[];
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.map((order) => normalizeOrder(order));
  } catch {
    return [];
  }
}

function writeOrders(orders: Order[]): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(ORDERS_STORAGE_KEY, JSON.stringify(orders));
  window.dispatchEvent(new CustomEvent(ORDERS_UPDATED_EVENT));
}

export function saveOrder(order: Order): void {
  const orders = readOrders();
  const existingIndex = orders.findIndex(
    (entry) => entry.orderNumber === order.orderNumber || entry.id === order.id,
  );

  if (existingIndex >= 0) {
    orders[existingIndex] = order;
  } else {
    orders.push(order);
  }

  writeOrders(orders);
}

export function getOrderByNumber(orderNumber: string): Order | null {
  const orders = readOrders();
  return orders.find((order) => order.orderNumber === orderNumber) ?? null;
}

export function getOrderById(orderId: string): Order | null {
  const orders = readOrders();
  return orders.find((order) => order.id === orderId) ?? null;
}

export function resolveOrderLookup(query: string): Order | null {
  const trimmed = query.trim();
  if (!trimmed) {
    return null;
  }

  const normalized = trimmed.toUpperCase();
  const orders = readOrders();

  return (
    orders.find(
      (order) =>
        order.id === trimmed ||
        order.orderNumber === trimmed ||
        order.orderNumber.toUpperCase() === normalized ||
        order.id.toUpperCase() === normalized,
    ) ?? null
  );
}

export function getAllOrders(): Order[] {
  return readOrders().sort(
    (a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime(),
  );
}

export function updateOrder(
  orderNumber: string,
  updater: (order: Order) => Order,
): Order | null {
  const orders = readOrders();
  const index = orders.findIndex((entry) => entry.orderNumber === orderNumber);

  if (index < 0) {
    return null;
  }

  const updated = updater(orders[index]);
  orders[index] = updated;
  writeOrders(orders);
  return updated;
}

export function updateOrderById(
  orderId: string,
  updater: (order: Order) => Order,
): Order | null {
  const orders = readOrders();
  const index = orders.findIndex((entry) => entry.id === orderId);

  if (index < 0) {
    return null;
  }

  const updated = updater(orders[index]);
  orders[index] = updated;
  writeOrders(orders);
  return updated;
}
