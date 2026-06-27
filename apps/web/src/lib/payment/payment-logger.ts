const LOG_PREFIX = "[CHINA ORDER TZ · Payment]";

export type PaymentLogEvent =
  | "config.loaded"
  | "simulate:start"
  | "simulate:server"
  | "simulate:paid"
  | "simulate:processing"
  | "simulate:complete"
  | "simulate:failed"
  | "initiate:start"
  | "initiate:complete"
  | "verify:poll"
  | "verify:paid"
  | "verify:failed"
  | "callback:received"
  | "stk:begin"
  | "stk:initiated"
  | "stk:confirmed"
  | "stk:complete"
  | "stk:failed";

export function logPaymentEvent(
  event: PaymentLogEvent,
  payload?: Record<string, unknown>,
): void {
  if (typeof window === "undefined") {
    return;
  }

  const entry = {
    event,
    at: new Date().toISOString(),
    ...payload,
  };

  console.info(`${LOG_PREFIX} ${event}`, entry);
}
