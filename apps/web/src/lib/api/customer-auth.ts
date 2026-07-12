const CUSTOMER_API_TOKEN_KEY = "china-order-tz-api-token";

export function getCustomerApiToken(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  const token = window.localStorage.getItem(CUSTOMER_API_TOKEN_KEY)?.trim();
  return token || null;
}

export function saveCustomerApiToken(token: string): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(CUSTOMER_API_TOKEN_KEY, token.trim());
}

export function clearCustomerApiToken(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.removeItem(CUSTOMER_API_TOKEN_KEY);
}

export const CUSTOMER_API_TOKEN_STORAGE_KEY = CUSTOMER_API_TOKEN_KEY;
