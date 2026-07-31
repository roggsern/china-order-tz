const CHECKOUT_PENDING_AUTH_KEY = "china-order-tz-checkout-pending-auth";

export function markCheckoutPendingAuth(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.setItem(CHECKOUT_PENDING_AUTH_KEY, "1");
}

export function consumeCheckoutPendingAuth(): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  const pending = window.sessionStorage.getItem(CHECKOUT_PENDING_AUTH_KEY) === "1";
  if (pending) {
    window.sessionStorage.removeItem(CHECKOUT_PENDING_AUTH_KEY);
  }

  return pending;
}

export function isCheckoutReturnPath(path: string | null | undefined): boolean {
  if (!path) {
    return false;
  }

  return path === "/checkout" || path.startsWith("/checkout/");
}
