import {
  normalizeAdminEmail,
  verifyAdminCredentials,
} from "@/lib/admin/credentials";

const ADMIN_SESSION_KEY = "china-order-tz-admin-session";

export type AdminSession = {
  email: string;
  authenticatedAt: string;
};

function readSession(): AdminSession | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(ADMIN_SESSION_KEY);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as AdminSession;
    if (!parsed?.email) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
}

function writeSession(session: AdminSession): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify(session));
}

export function getAdminSession(): AdminSession | null {
  return readSession();
}

export function isAdminAuthenticated(): boolean {
  return readSession() !== null;
}

export function authenticateAdmin(email: string, password: string): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  if (!verifyAdminCredentials(email, password)) {
    return false;
  }

  writeSession({
    email: normalizeAdminEmail(email),
    authenticatedAt: new Date().toISOString(),
  });
  return true;
}

export function signOutAdmin(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.removeItem(ADMIN_SESSION_KEY);
}
