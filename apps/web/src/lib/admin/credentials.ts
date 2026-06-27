/**
 * Default admin credentials for the client-side admin gate (demo / local use).
 *
 * Override via environment variables in apps/web/.env.local:
 *   NEXT_PUBLIC_ADMIN_EMAIL=admin@china.com
 *   NEXT_PUBLIC_ADMIN_PASSWORD=admin123
 *
 * Production should replace this with server-side auth — these values are
 * bundled into the client when using NEXT_PUBLIC_* vars.
 */
export const DEFAULT_ADMIN_EMAIL =
  process.env.NEXT_PUBLIC_ADMIN_EMAIL ?? "admin@china.com";

export const DEFAULT_ADMIN_PASSWORD =
  process.env.NEXT_PUBLIC_ADMIN_PASSWORD ?? "admin123";

export function normalizeAdminEmail(email: string): string {
  return email.trim().toLowerCase();
}

export function verifyAdminCredentials(email: string, password: string): boolean {
  return (
    normalizeAdminEmail(email) === normalizeAdminEmail(DEFAULT_ADMIN_EMAIL) &&
    password === DEFAULT_ADMIN_PASSWORD
  );
}
