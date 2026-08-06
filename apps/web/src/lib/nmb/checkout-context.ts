import type { NmbCheckoutContext } from "@/lib/nmb/types";

const SESSION_STORAGE_KEY = "china-order-tz-nmb-checkout";
const DURABLE_STORAGE_KEY = "china-order-tz-nmb-checkout-durable";
const PENDING_PAYMENT_KEY = "china-order-tz-nmb-pending-payment-id";
const DURABLE_PENDING_PAYMENT_KEY = "china-order-tz-nmb-pending-payment-id-durable";

function canUseStorage(): boolean {
  return typeof window !== "undefined";
}

function readJson(storage: Storage, key: string): NmbCheckoutContext | null {
  try {
    const raw = storage.getItem(key);
    if (!raw) {
      return null;
    }

    return JSON.parse(raw) as NmbCheckoutContext;
  } catch {
    return null;
  }
}

function writeJson(storage: Storage, key: string, value: NmbCheckoutContext): void {
  storage.setItem(key, JSON.stringify(value));
}

function mergeContext(
  existing: NmbCheckoutContext | null,
  patch: Partial<NmbCheckoutContext> & Pick<NmbCheckoutContext, "paymentId">,
): NmbCheckoutContext {
  return {
    ...existing,
    ...patch,
    paymentId: patch.paymentId || existing?.paymentId || "",
  };
}

export function saveNmbCheckoutContext(context: NmbCheckoutContext): void {
  if (!canUseStorage()) {
    return;
  }

  const next = mergeContext(readNmbCheckoutContext(), context);
  writeJson(window.sessionStorage, SESSION_STORAGE_KEY, next);
  writeJson(window.localStorage, DURABLE_STORAGE_KEY, next);

  const paymentTransactionId = next.paymentTransactionId ?? next.paymentId;
  if (paymentTransactionId) {
    setNmbPendingPaymentId(paymentTransactionId);
  }
}

export function readNmbCheckoutContext(): NmbCheckoutContext | null {
  if (!canUseStorage()) {
    return null;
  }

  return (
    readJson(window.sessionStorage, SESSION_STORAGE_KEY) ??
    readJson(window.localStorage, DURABLE_STORAGE_KEY)
  );
}

export function patchNmbCheckoutContext(patch: Partial<NmbCheckoutContext>): NmbCheckoutContext | null {
  const existing = readNmbCheckoutContext();
  if (!existing && !patch.paymentId && !patch.paymentTransactionId) {
    return null;
  }

  const paymentId =
    patch.paymentId ??
    patch.paymentTransactionId ??
    existing?.paymentId ??
    existing?.paymentTransactionId;

  if (!paymentId) {
    return null;
  }

  const next = mergeContext(existing, { ...patch, paymentId });
  saveNmbCheckoutContext(next);
  return next;
}

export function clearNmbCheckoutContext(): void {
  if (!canUseStorage()) {
    return;
  }

  window.sessionStorage.removeItem(SESSION_STORAGE_KEY);
  window.localStorage.removeItem(DURABLE_STORAGE_KEY);
  window.sessionStorage.removeItem(PENDING_PAYMENT_KEY);
  window.localStorage.removeItem(DURABLE_PENDING_PAYMENT_KEY);
}

export function setNmbPendingPaymentId(paymentId: string): void {
  if (!canUseStorage() || !paymentId.trim()) {
    return;
  }

  const value = paymentId.trim();
  window.sessionStorage.setItem(PENDING_PAYMENT_KEY, value);
  window.localStorage.setItem(DURABLE_PENDING_PAYMENT_KEY, value);
}

/** Non-destructive read used during return recovery. */
export function peekNmbPendingPaymentId(): string | null {
  if (!canUseStorage()) {
    return null;
  }

  return (
    window.sessionStorage.getItem(PENDING_PAYMENT_KEY)?.trim() ||
    window.localStorage.getItem(DURABLE_PENDING_PAYMENT_KEY)?.trim() ||
    null
  );
}

/** @deprecated Prefer peekNmbPendingPaymentId + clearNmbCheckoutContext after success. */
export function consumeNmbPendingPaymentId(): string | null {
  const paymentId = peekNmbPendingPaymentId();

  if (!canUseStorage() || !paymentId) {
    return paymentId;
  }

  window.sessionStorage.removeItem(PENDING_PAYMENT_KEY);
  window.localStorage.removeItem(DURABLE_PENDING_PAYMENT_KEY);

  return paymentId;
}
