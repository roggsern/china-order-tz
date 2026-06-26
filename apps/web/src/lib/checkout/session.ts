export type CheckoutSession = {
  orderId: string;
  orderNumber: string;
};

const CHECKOUT_SESSION_KEY = "china-order-tz-checkout-session";

export function setCheckoutSession(session: CheckoutSession): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.setItem(CHECKOUT_SESSION_KEY, JSON.stringify(session));
}

export function getCheckoutSession(): CheckoutSession | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.sessionStorage.getItem(CHECKOUT_SESSION_KEY);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as CheckoutSession;
    if (!parsed.orderId || !parsed.orderNumber) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
}

export function clearCheckoutSession(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.removeItem(CHECKOUT_SESSION_KEY);
}
