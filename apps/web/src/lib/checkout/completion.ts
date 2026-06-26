import { PAYMENT_STATUS } from "@/lib/types/payment";
import { getOrderById as getStoredOrderById } from "@/lib/payment/order-storage";

const CART_CLEARED_PREFIX = "china-order-tz-cart-cleared-";

function readStorage(key: string): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return window.localStorage.getItem(key);
}

function writeStorage(key: string, value: string): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(key, value);
}

export function wasCartClearedForOrder(orderId: string): boolean {
  return readStorage(`${CART_CLEARED_PREFIX}${orderId}`) === "1";
}

/** Clears purchased items exactly once per order — preserves saved-for-later. */
export function clearCartOnceForOrder(
  orderId: string,
  clearPurchasedItems: () => void,
): void {
  if (typeof window === "undefined" || wasCartClearedForOrder(orderId)) {
    return;
  }

  clearPurchasedItems();
  writeStorage(`${CART_CLEARED_PREFIX}${orderId}`, "1");
}

/**
 * Cart clears ONLY when paymentStatus === "paid".
 * Never call on checkout navigation or payment initiation.
 */
export function clearCartIfOrderPaid(
  orderId: string,
  clearPurchasedItems: () => void,
): void {
  const order = getStoredOrderById(orderId);
  if (!order || order.paymentStatus !== PAYMENT_STATUS.PAID) {
    return;
  }

  clearCartOnceForOrder(orderId, clearPurchasedItems);
}

export function isOrderPaid(orderId: string): boolean {
  const order = getStoredOrderById(orderId);
  return order?.paymentStatus === PAYMENT_STATUS.PAID;
}
