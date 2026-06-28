/** Runtime environment helpers — single source for app URLs and production checks. */

export function isProduction(): boolean {
  return process.env.NODE_ENV === "production";
}

export function isDevelopment(): boolean {
  return process.env.NODE_ENV === "development";
}

export function isVercelDeployment(): boolean {
  return process.env.NEXT_PUBLIC_VERCEL === "1" || process.env.VERCEL === "1";
}

/** Public storefront / Next.js origin (no trailing slash). */
export function getAppUrl(): string {
  const fromEnv =
    process.env.NEXT_PUBLIC_APP_URL?.trim() || process.env.APP_URL?.trim() || "";

  if (fromEnv) {
    return fromEnv.replace(/\/$/, "");
  }

  if (isDevelopment()) {
    return "http://localhost:3000";
  }

  return "";
}

/** Backend API origin (Laravel or BFF). Falls back to app URL in production. */
export function getApiUrl(): string {
  const fromEnv =
    process.env.NEXT_PUBLIC_API_URL?.trim() || process.env.API_URL?.trim() || "";

  if (fromEnv) {
    return fromEnv.replace(/\/$/, "");
  }

  const appUrl = getAppUrl();
  if (appUrl) {
    return appUrl;
  }

  if (isDevelopment()) {
    return "http://localhost:8000";
  }

  return "";
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
