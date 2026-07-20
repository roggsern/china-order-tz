/** Runtime environment helpers — single source for app URLs and production checks. */

export type ApiUrlResolution = {
  url: string;
  source: string;
};

function normalizeOrigin(url: string): string {
  return url.replace(/\/$/, "");
}

export function isProduction(): boolean {
  return process.env.NODE_ENV === "production";
}

export function isDevelopment(): boolean {
  return process.env.NODE_ENV === "development";
}

/**
 * Demo/local admin order status authority (localStorage + Next.js store).
 * Production must use Laravel OrderLifecycleEngine only.
 * Opt-in via NEXT_PUBLIC_ADMIN_LOCAL_ORDER_AUTHORITY=true (never default in production).
 */
export function isAdminLocalOrderAuthorityEnabled(): boolean {
  const flag = process.env.NEXT_PUBLIC_ADMIN_LOCAL_ORDER_AUTHORITY?.trim().toLowerCase();
  if (flag === "true" || flag === "1") {
    return !isProduction();
  }
  return false;
}

export function isVercelDeployment(): boolean {
  return process.env.NEXT_PUBLIC_VERCEL === "1" || process.env.VERCEL === "1";
}

function isBrowserRuntime(): boolean {
  return typeof window !== "undefined";
}

/** Public storefront / Next.js origin (no trailing slash). */
export function getAppUrl(): string {
  const fromEnv =
    process.env.NEXT_PUBLIC_APP_URL?.trim() || process.env.APP_URL?.trim() || "";

  if (fromEnv) {
    return normalizeOrigin(fromEnv);
  }

  if (isDevelopment()) {
    return "http://localhost:3000";
  }

  return "";
}

/**
 * Resolves the browser-facing Laravel API origin.
 * Used for client-side requests and URLs emitted into HTML (e.g. image src).
 */
export function resolvePublicApiUrl(): ApiUrlResolution {
  const nextPublicApiUrl = process.env.NEXT_PUBLIC_API_URL?.trim();

  if (nextPublicApiUrl) {
    return {
      url: normalizeOrigin(nextPublicApiUrl),
      source: "NEXT_PUBLIC_API_URL",
    };
  }

  const apiUrl = process.env.API_URL?.trim();

  if (apiUrl) {
    return {
      url: normalizeOrigin(apiUrl),
      source: "API_URL",
    };
  }

  if (isDevelopment()) {
    return {
      url: "http://localhost:8000",
      source: "development default (NEXT_PUBLIC_API_URL and API_URL are unset)",
    };
  }

  return {
    url: "",
    source: "unset (NEXT_PUBLIC_API_URL and API_URL are required in production)",
  };
}

/** Browser-facing Laravel API origin. */
export function getPublicApiUrl(): string {
  return resolvePublicApiUrl().url;
}

/**
 * Resolves the Laravel API origin for server-side requests (SSR, Route Handlers, server utilities).
 * Prefers the Docker/internal network URL when configured.
 */
export function resolveServerApiUrl(): ApiUrlResolution {
  const internalApiUrl = process.env.API_INTERNAL_URL?.trim();

  if (internalApiUrl) {
    return {
      url: normalizeOrigin(internalApiUrl),
      source: "API_INTERNAL_URL",
    };
  }

  const apiUrl = process.env.API_URL?.trim();

  if (apiUrl) {
    return {
      url: normalizeOrigin(apiUrl),
      source: "API_URL",
    };
  }

  const publicResolution = resolvePublicApiUrl();

  if (publicResolution.url) {
    return {
      url: publicResolution.url,
      source: `${publicResolution.source} (server fallback)`,
    };
  }

  return publicResolution;
}

/** Server-side Laravel API origin (SSR, Route Handlers, server utilities). */
export function getServerApiUrl(): string {
  const resolution = resolveServerApiUrl();

  if (isDebugLoggingEnabled()) {
    if (resolution.url) {
      console.info(`[getServerApiUrl] ${resolution.url} ← ${resolution.source}`);
    } else {
      console.warn(`[getServerApiUrl] no API URL configured ← ${resolution.source}`);
    }
  }

  return resolution.url;
}

/**
 * Context-aware Laravel API origin.
 * - Server: API_INTERNAL_URL → API_URL → NEXT_PUBLIC_API_URL
 * - Browser: NEXT_PUBLIC_API_URL → API_URL
 */
export function resolveApiUrl(): ApiUrlResolution {
  return isBrowserRuntime() ? resolvePublicApiUrl() : resolveServerApiUrl();
}

/** Backend API origin — automatically selects internal URL on the server, public URL in the browser. */
export function getApiUrl(): string {
  if (isBrowserRuntime()) {
    return getPublicApiUrl();
  }

  return getServerApiUrl();
}

export function requireAppUrl(): string {
  const url = getAppUrl();
  if (!url) {
    throw new Error("NEXT_PUBLIC_APP_URL must be set in production.");
  }
  return url;
}

export function isDebugLoggingEnabled(): boolean {
  if (isDevelopment()) {
    return true;
  }
  return process.env.ENABLE_DEBUG_LOGS === "true";
}
