/**
 * Safe post-auth redirect helpers (frontend only).
 * Accepts same-origin relative paths; rejects open redirects.
 */

const FALLBACK_RETURN_PATH = "/account";

export function sanitizeReturnUrl(raw: string | null | undefined): string | null {
  if (!raw) return null;

  let decoded = raw.trim();
  try {
    decoded = decodeURIComponent(decoded);
  } catch {
    // keep raw trim if decode fails
  }

  if (!decoded.startsWith("/") || decoded.startsWith("//")) {
    return null;
  }

  // Block protocol-relative and absolute URLs disguised as paths
  if (/^[a-z][a-z0-9+.-]*:/i.test(decoded)) {
    return null;
  }

  // Prefer storefront destinations; avoid looping on auth pages
  const pathOnly = decoded.split("?")[0]?.split("#")[0] ?? decoded;
  if (
    pathOnly === "/login" ||
    pathOnly === "/register" ||
    pathOnly === "/forgot-password"
  ) {
    return null;
  }

  return decoded;
}

export function resolvePostAuthRedirect(raw: string | null | undefined): string {
  return sanitizeReturnUrl(raw) ?? FALLBACK_RETURN_PATH;
}

export function buildLoginHref(returnUrl?: string | null): string {
  const safe = sanitizeReturnUrl(returnUrl);
  if (!safe) return "/login";
  return `/login?returnUrl=${encodeURIComponent(safe)}`;
}

export function buildRegisterHref(returnUrl?: string | null): string {
  const safe = sanitizeReturnUrl(returnUrl);
  if (!safe) return "/register";
  return `/register?returnUrl=${encodeURIComponent(safe)}`;
}

export function withPreservedReturnUrl(
  href: "/login" | "/register" | "/forgot-password",
  returnUrl?: string | null,
): string {
  const safe = sanitizeReturnUrl(returnUrl);
  if (!safe) return href;
  return `${href}?returnUrl=${encodeURIComponent(safe)}`;
}
