import { loadCartState, saveCartState } from "@/lib/cart/storage";

const CART_LOCKED_PREFIX = "china-order-tz-cart-locked-";

function readFlag(key: string): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  return window.localStorage.getItem(key) === "1";
}

function writeFlag(key: string): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(key, "1");
}

export function wasCartLockedForOrder(orderId: string): boolean {
  return readFlag(`${CART_LOCKED_PREFIX}${orderId}`);
}

/** Clears active cart items in storage — preserves saved-for-later. Idempotent per order. */
export function lockCartForOrderInStorage(orderId: string): void {
  if (typeof window === "undefined" || wasCartLockedForOrder(orderId)) {
    return;
  }

  const current = loadCartState();
  saveCartState({
    ...current,
    items: [],
    discount: 0,
  });
  writeFlag(`${CART_LOCKED_PREFIX}${orderId}`);
}

/** Syncs React cart state after storage lock — call from client components. */
export function lockCartForOrder(
  orderId: string,
  clearPurchasedItems?: () => void,
): void {
  lockCartForOrderInStorage(orderId);
  clearPurchasedItems?.();
}
