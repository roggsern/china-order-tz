const CUSTOMER_SESSION_KEY = "china-order-tz-customer-session";

export type CustomerSession = {
  email: string;
  name?: string;
};

export function getCustomerSession(): CustomerSession | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(CUSTOMER_SESSION_KEY);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as CustomerSession;
    return parsed?.email ? parsed : null;
  } catch {
    return null;
  }
}

export function isCustomerLoggedIn(): boolean {
  return getCustomerSession() !== null;
}

export function saveCustomerSession(session: CustomerSession): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(CUSTOMER_SESSION_KEY, JSON.stringify(session));
}

export function clearCustomerSession(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.removeItem(CUSTOMER_SESSION_KEY);
}

export const CUSTOMER_SESSION_STORAGE_KEY = CUSTOMER_SESSION_KEY;
