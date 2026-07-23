import { normalizeAdminEmail } from "@/lib/admin/credentials";

const ADMIN_SESSION_KEY = "china-order-tz-admin-session";

export type AdminSession = {
  email: string;
  name?: string;
  authenticatedAt: string;
};

export type AdminLoginResult =
  | { ok: true; session: AdminSession }
  | { ok: false; message: string };

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

  // UI/display only — authentication is the HttpOnly Sanctum cookie (RC1-G4A).
  window.localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify(session));
}

export function getAdminSession(): AdminSession | null {
  return readSession();
}

export function isAdminAuthenticated(): boolean {
  return readSession() !== null;
}

/**
 * Authenticates against Laravel via the Next.js BFF (POST /api/admin/login).
 * Does not accept hardcoded credentials — backend verifies email/password.
 * Does not set client-writable auth cookies.
 */
export async function authenticateAdmin(
  email: string,
  password: string,
): Promise<AdminLoginResult> {
  if (typeof window === "undefined") {
    return { ok: false, message: "Admin sign-in is only available in the browser." };
  }

  const response = await fetch("/api/admin/login", {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      email: normalizeAdminEmail(email),
      password,
    }),
    cache: "no-store",
  });

  const payload = (await response.json().catch(() => null)) as {
    success?: boolean;
    message?: string;
    data?: { email?: string; name?: string };
    errors?: Record<string, string[]>;
  } | null;

  if (!response.ok || payload?.success === false) {
    const firstError = payload?.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;

    return {
      ok: false,
      message:
        firstError?.trim() ||
        payload?.message?.trim() ||
        "Invalid email or password. Please check your credentials and try again.",
    };
  }

  const session: AdminSession = {
    email: normalizeAdminEmail(payload?.data?.email || email),
    name: payload?.data?.name,
    authenticatedAt: new Date().toISOString(),
  };

  writeSession(session);
  return { ok: true, session };
}

export async function signOutAdmin(): Promise<void> {
  if (typeof window === "undefined") {
    return;
  }

  try {
    await fetch("/api/admin/logout", {
      method: "POST",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
  } catch {
    // Clear local session even if logout proxy fails.
  }

  window.localStorage.removeItem(ADMIN_SESSION_KEY);
}
