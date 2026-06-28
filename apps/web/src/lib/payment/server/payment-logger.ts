import { isDebugLoggingEnabled } from "@/lib/config/env";

const LOG_PREFIX = "[CHINA ORDER TZ · Payment API]";

export function logServerPaymentEvent(
  event: string,
  payload?: Record<string, unknown>,
): void {
  if (!isDebugLoggingEnabled()) {
    return;
  }

  console.info(`${LOG_PREFIX} ${event}`, {
    at: new Date().toISOString(),
    ...payload,
  });
}
