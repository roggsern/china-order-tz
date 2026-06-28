import { getAppUrl, isProduction, isVercelDeployment } from "@/lib/config/env";

type ResolveWsUrlInput = {
  /** Service-specific override, e.g. NEXT_PUBLIC_ADMIN_WS_URL */
  explicitUrl?: string;
  path: string;
  searchParams?: Record<string, string>;
};

function appendSearchParams(url: URL, searchParams?: Record<string, string>): string {
  if (searchParams) {
    for (const [key, value] of Object.entries(searchParams)) {
      url.searchParams.set(key, value);
    }
  }
  return url.toString();
}

/** WebSocket origin derived from NEXT_PUBLIC_WS_BASE_URL or NEXT_PUBLIC_APP_URL. */
export function getWsBaseOrigin(): string | null {
  const explicitBase = process.env.NEXT_PUBLIC_WS_BASE_URL?.trim();
  if (explicitBase) {
    return explicitBase.replace(/\/$/, "");
  }

  const appUrl = getAppUrl();
  if (!appUrl) {
    return null;
  }

  try {
    const parsed = new URL(appUrl);
    parsed.protocol = parsed.protocol === "https:" ? "wss:" : "ws:";
    return parsed.origin;
  } catch {
    return null;
  }
}

/** Whether the deployment should skip same-origin WebSocket (serverless / no WS server). */
export function isWebSocketUnavailable(): boolean {
  if (isVercelDeployment()) {
    return true;
  }

  if (isProduction()) {
    const hasExternalWs =
      Boolean(process.env.NEXT_PUBLIC_WS_BASE_URL?.trim()) ||
      Boolean(process.env.NEXT_PUBLIC_ADMIN_WS_URL?.trim()) ||
      Boolean(process.env.NEXT_PUBLIC_ORDER_TRACKING_WS_URL?.trim()) ||
      Boolean(process.env.NEXT_PUBLIC_NOTIFICATIONS_WS_URL?.trim());

    return !hasExternalWs;
  }

  return false;
}

/** Resolve a WebSocket URL for client subscriptions. Returns null when WS is unavailable. */
export function resolveWebSocketUrl(input: ResolveWsUrlInput): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  if (isWebSocketUnavailable() && !input.explicitUrl?.trim() && !getWsBaseOrigin()) {
    return null;
  }

  const explicit = input.explicitUrl?.trim();
  if (explicit) {
    return appendSearchParams(new URL(explicit), input.searchParams);
  }

  const baseOrigin = getWsBaseOrigin();
  if (baseOrigin) {
    const url = new URL(`${baseOrigin}${input.path}`);
    return appendSearchParams(url, input.searchParams);
  }

  if (isWebSocketUnavailable()) {
    return null;
  }

  const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
  const url = new URL(`${protocol}//${window.location.host}${input.path}`);
  return appendSearchParams(url, input.searchParams);
}
