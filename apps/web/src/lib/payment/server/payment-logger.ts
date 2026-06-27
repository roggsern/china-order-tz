const LOG_PREFIX = "[CHINA ORDER TZ · Payment API]";

export function logServerPaymentEvent(
  event: string,
  payload?: Record<string, unknown>,
): void {
  console.info(`${LOG_PREFIX} ${event}`, {
    at: new Date().toISOString(),
    ...payload,
  });
}
