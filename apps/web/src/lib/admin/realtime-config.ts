import { isProduction, isVercelDeployment } from "@/lib/config/env";
import { getWsBaseOrigin } from "@/lib/realtime/ws-config";

export type AdminRealtimeMode = "websocket" | "polling" | "auto";
export type AdminRealtimeTransport = "websocket" | "polling";

const VALID_MODES: AdminRealtimeMode[] = ["websocket", "polling", "auto"];

function readMode(): AdminRealtimeMode {
  const raw = process.env.NEXT_PUBLIC_ADMIN_REALTIME_MODE?.trim().toLowerCase();
  if (raw && VALID_MODES.includes(raw as AdminRealtimeMode)) {
    return raw as AdminRealtimeMode;
  }
  return "auto";
}

function hasExternalWebSocketConfig(): boolean {
  return Boolean(
    process.env.NEXT_PUBLIC_ADMIN_WS_URL?.trim() ||
      process.env.NEXT_PUBLIC_WS_BASE_URL?.trim() ||
      getWsBaseOrigin(),
  );
}

/** Client-side transport selection for admin order updates. */
export function resolveAdminRealtimeTransport(): AdminRealtimeTransport {
  const mode = readMode();

  if (mode === "polling") {
    return "polling";
  }

  if (mode === "websocket") {
    return "websocket";
  }

  // auto: use WebSocket when an external/base URL is configured or in local dev
  if (hasExternalWebSocketConfig()) {
    return "websocket";
  }

  if (isVercelDeployment() || isProduction()) {
    return "polling";
  }

  return "websocket";
}

export function getAdminOrdersPollIntervalMs(hidden: boolean): number {
  const visibleMs = Number.parseInt(
    process.env.NEXT_PUBLIC_ADMIN_POLL_INTERVAL_MS ?? "5000",
    10,
  );
  const hiddenMs = Number.parseInt(
    process.env.NEXT_PUBLIC_ADMIN_POLL_INTERVAL_HIDDEN_MS ?? "30000",
    10,
  );

  return hidden ? hiddenMs : visibleMs;
}

export function getAdminWsFallbackFailuresBeforePolling(): number {
  return Number.parseInt(process.env.NEXT_PUBLIC_ADMIN_WS_FALLBACK_FAILURES ?? "3", 10);
}
