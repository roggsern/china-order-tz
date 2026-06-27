const ADMIN_SESSION_KEY = "china-order-tz-admin-session";

/** Demo admin PIN — replace with server auth in production. */
const ADMIN_PIN = process.env.NEXT_PUBLIC_ADMIN_PIN ?? "china-order-admin";

export function isAdminAuthenticated(): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  return window.sessionStorage.getItem(ADMIN_SESSION_KEY) === "authenticated";
}

export function authenticateAdmin(pin: string): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  if (pin.trim() !== ADMIN_PIN) {
    return false;
  }

  window.sessionStorage.setItem(ADMIN_SESSION_KEY, "authenticated");
  return true;
}

export function signOutAdmin(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.removeItem(ADMIN_SESSION_KEY);
}
