/**
 * Admin session cookie names (RC1-G4A).
 *
 * Only ADMIN_TOKEN_COOKIE is authoritative for authentication.
 * It is set HttpOnly by POST /api/admin/login and never written from client JS.
 */
export const ADMIN_TOKEN_COOKIE = "china-order-tz-admin-token";

/**
 * @deprecated Legacy non-HttpOnly gate cookie — must not be trusted for auth.
 * Cleared on login/logout for migration; never used as an auth signal.
 */
export const ADMIN_AUTH_COOKIE = "china-order-tz-admin-auth";

/** Default admin PAT cookie lifetime (seconds). Align with SANCTUM_ADMIN_EXPIRATION_MINUTES (480). */
export const ADMIN_TOKEN_MAX_AGE_SECONDS_DEFAULT = 60 * 60 * 8;

export function adminTokenMaxAgeSeconds(): number {
  const raw = process.env.ADMIN_TOKEN_MAX_AGE_SECONDS?.trim();
  if (raw) {
    const parsed = Number.parseInt(raw, 10);
    if (Number.isFinite(parsed) && parsed > 0) {
      return parsed;
    }
  }

  return ADMIN_TOKEN_MAX_AGE_SECONDS_DEFAULT;
}

/** @deprecated Use adminTokenMaxAgeSeconds() */
export const ADMIN_AUTH_COOKIE_MAX_AGE_SECONDS = ADMIN_TOKEN_MAX_AGE_SECONDS_DEFAULT;

/**
 * Pure gate used by middleware / BFF — only a non-empty Sanctum token counts.
 * Client-set forgeable cookies must never satisfy this.
 */
export function hasAdminSanctumSessionToken(
  tokenCookieValue: string | undefined | null,
): boolean {
  return Boolean(tokenCookieValue?.trim());
}
