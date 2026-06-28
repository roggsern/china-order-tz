import { isProduction } from "@/lib/config/env";

/**
 * Admin credentials for the client-side admin gate.
 *
 * Production requires NEXT_PUBLIC_ADMIN_EMAIL and NEXT_PUBLIC_ADMIN_PASSWORD.
 * Development falls back to demo credentials when unset.
 */
function getAdminEmail(): string {
  const fromEnv = process.env.NEXT_PUBLIC_ADMIN_EMAIL?.trim();
  if (fromEnv) {
    return fromEnv;
  }
  if (isProduction()) {
    return "";
  }
  return "admin@china.com";
}

function getAdminPassword(): string {
  const fromEnv = process.env.NEXT_PUBLIC_ADMIN_PASSWORD?.trim();
  if (fromEnv) {
    return fromEnv;
  }
  if (isProduction()) {
    return "";
  }
  return "admin123";
}

export const DEFAULT_ADMIN_EMAIL = getAdminEmail();
export const DEFAULT_ADMIN_PASSWORD = getAdminPassword();

export function normalizeAdminEmail(email: string): string {
  return email.trim().toLowerCase();
}

export function verifyAdminCredentials(email: string, password: string): boolean {
  const adminEmail = getAdminEmail();
  const adminPassword = getAdminPassword();

  if (!adminEmail || !adminPassword) {
    return false;
  }

  return (
    normalizeAdminEmail(email) === normalizeAdminEmail(adminEmail) &&
    password === adminPassword
  );
}

export function isAdminConfigured(): boolean {
  return Boolean(getAdminEmail() && getAdminPassword());
}
