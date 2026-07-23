import { isProduction } from "@/lib/config/env";

/**
 * Display helpers for the admin login form.
 * Real authentication is always performed by Laravel via POST /api/admin/login.
 *
 * Dev defaults match AdminSeeder / ADMIN_SEED_*.
 * ADMIN_API_EMAIL / ADMIN_API_PASSWORD are never used for BFF authentication (RC1-G4A).
 */
function getAdminEmailHint(): string {
  const fromEnv = process.env.NEXT_PUBLIC_ADMIN_EMAIL?.trim();
  if (fromEnv) {
    return fromEnv;
  }
  if (isProduction()) {
    return "";
  }
  return "admin@chinaordertz.com";
}

export const DEFAULT_ADMIN_EMAIL = getAdminEmailHint();

export function normalizeAdminEmail(email: string): string {
  return email.trim().toLowerCase();
}

export function isAdminConfigured(): boolean {
  return !isProduction() || Boolean(process.env.NEXT_PUBLIC_ADMIN_EMAIL?.trim());
}
