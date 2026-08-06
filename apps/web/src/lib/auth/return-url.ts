/**
 * Safe post-auth redirect helpers (frontend only).
 * Accepts same-origin relative paths; rejects open redirects.
 */

const FALLBACK_RETURN_PATH = "/account";

const AUTH_ENTRY_PATHS = new Set(["/login", "/register", "/forgot-password"]);

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
  if (AUTH_ENTRY_PATHS.has(pathOnly)) {
    return null;
  }

  return decoded;
}

/**
 * Build a safe return path from the current storefront location (pathname + query).
 * Returns null on auth pages so login does not loop.
 */
export function buildCurrentReturnPath(
  pathname: string | null | undefined,
  search?: string | null,
): string | null {
  const rawPath = (pathname ?? "").trim() || "/";
  if (!rawPath.startsWith("/") || rawPath.startsWith("//")) {
    return null;
  }

  const pathOnly = rawPath.split("?")[0]?.split("#")[0] || "/";
  if (AUTH_ENTRY_PATHS.has(pathOnly)) {
    return null;
  }

  let query = (search ?? "").trim();
  if (query.startsWith("?")) {
    query = query.slice(1);
  }

  const combined = query ? `${pathOnly}?${query}` : pathOnly;
  return sanitizeReturnUrl(combined);
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

/**
 * Rewrite bare /login or /register hrefs so they preserve post-auth return context.
 * Leaves all other hrefs unchanged.
 */
export function resolveAuthEntryHref(
  href: string | null | undefined,
  returnUrl?: string | null,
): string {
  const raw = (href ?? "").trim();
  if (!raw) {
    return "#";
  }

  const pathOnly = raw.split("?")[0]?.split("#")[0] ?? raw;

  if (pathOnly === "/login") {
    return buildLoginHref(returnUrl);
  }

  if (pathOnly === "/register") {
    return buildRegisterHref(returnUrl);
  }

  return raw;
}

/** Same-origin relative path (single leading slash, not protocol-relative). */
export type InternalRelativeHref = `/${string}`;

export function withPreservedReturnUrl(
  href: InternalRelativeHref,
  returnUrl?: string | null,
): string {
  if (!href.startsWith("/") || href.startsWith("//")) {
    return href;
  }

  const safe = sanitizeReturnUrl(returnUrl);
  if (!safe) return href;
  return `${href}?returnUrl=${encodeURIComponent(safe)}`;
}
