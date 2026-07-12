import type { NmbCheckoutContext } from "@/lib/nmb/types";

const STORAGE_KEY = "china-order-tz-nmb-checkout";

export function saveNmbCheckoutContext(context: NmbCheckoutContext): void {
  if (typeof window === "undefined") {
    return;
  }

  const existing = readNmbCheckoutContext();
  window.sessionStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({
      ...existing,
      ...context,
    }),
  );
}

export function readNmbCheckoutContext(): NmbCheckoutContext | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.sessionStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return null;
    }

    return JSON.parse(raw) as NmbCheckoutContext;
  } catch {
    return null;
  }
}

export function patchNmbCheckoutContext(patch: Partial<NmbCheckoutContext>): NmbCheckoutContext | null {
  const existing = readNmbCheckoutContext();

  if (!existing) {
    return null;
  }

  const next = { ...existing, ...patch };
  saveNmbCheckoutContext(next);
  return next;
}

export function clearNmbCheckoutContext(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.removeItem(STORAGE_KEY);
}

const PENDING_PAYMENT_KEY = "china-order-tz-nmb-pending-payment-id";

export function setNmbPendingPaymentId(paymentId: string): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.setItem(PENDING_PAYMENT_KEY, paymentId);
}

export function consumeNmbPendingPaymentId(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  const paymentId = window.sessionStorage.getItem(PENDING_PAYMENT_KEY)?.trim() || null;

  if (paymentId) {
    window.sessionStorage.removeItem(PENDING_PAYMENT_KEY);
  }

  return paymentId;
}
