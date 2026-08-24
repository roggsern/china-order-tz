import type { ActivePaymentTransactionRef } from '../models/types';

export type { ActivePaymentTransactionRef };

export type PayNowView =
  | { kind: 'selector' }
  | { kind: 'paid' }
  | { kind: 'recovery'; transaction: ActivePaymentTransactionRef }
  | { kind: 'not_payable'; reason: 'paid' | 'cancelled' | 'other' };

const TERMINAL_UNPAYABLE_ORDER_STATUSES = new Set([
  'cancelled',
  'refunded',
  'refund_pending',
]);

const PAID_OR_FULFILLING_ORDER_STATUSES = new Set([
  'paid',
  'confirmed',
  'processing',
  'shipped',
  'delivered',
  'completed',
]);

export function isActivePaymentTransactionStatus(status: string): boolean {
  return status === 'pending' || status === 'processing';
}

export function isTerminalFailedPaymentStatus(status: string): boolean {
  return status === 'failed' || status === 'cancelled' || status === 'expired' || status === 'voided';
}

export function isSuccessfulPaymentTransactionStatus(status: string): boolean {
  return status === 'successful';
}

export function resolveRefreshedTransactionView(
  status: string,
): 'paid' | 'selector' | 'recovery' {
  if (isSuccessfulPaymentTransactionStatus(status)) {
    return 'paid';
  }

  if (isTerminalFailedPaymentStatus(status)) {
    return 'selector';
  }

  return 'recovery';
}

function isTerminalUnpayableOrder(
  orderStatus: string,
  paymentStatus?: string | null,
): boolean {
  return (
    TERMINAL_UNPAYABLE_ORDER_STATUSES.has(orderStatus) ||
    paymentStatus === 'refunded'
  );
}

/**
 * Classify backend Pay Now state. Does not start a provider and does not
 * read local/session storage. Cancelled/refunded order status always wins
 * over a stale pending/processing transaction.
 */
export function resolvePayNowView(input: {
  canPay: boolean;
  orderStatus: string;
  paymentStatus?: string | null;
  activeTransaction?: ActivePaymentTransactionRef | null;
}): PayNowView {
  if (isTerminalUnpayableOrder(input.orderStatus, input.paymentStatus)) {
    return { kind: 'not_payable', reason: 'cancelled' };
  }

  if (
    input.paymentStatus === 'paid' ||
    PAID_OR_FULFILLING_ORDER_STATUSES.has(input.orderStatus)
  ) {
    if (!input.canPay) {
      return { kind: 'paid' };
    }
  }

  if (!input.canPay) {
    return { kind: 'not_payable', reason: 'other' };
  }

  const active = input.activeTransaction;
  if (active && isActivePaymentTransactionStatus(active.status)) {
    return { kind: 'recovery', transaction: active };
  }

  return { kind: 'selector' };
}

type PaymentInProgressErrorLike = {
  code?: string | null;
  message?: string | null;
  paymentTransactionId?: string | null;
  paymentTransactionStatus?: string | null;
  provider?: string | null;
  raw?: Record<string, unknown> | null;
};

function asErrorLike(error: unknown): PaymentInProgressErrorLike | null {
  if (!error || typeof error !== 'object') return null;
  return error as PaymentInProgressErrorLike;
}

export function isPaymentInProgressError(error: unknown): boolean {
  const like = asErrorLike(error);
  if (!like) return false;

  if (like.code === 'payment_in_progress') {
    return true;
  }

  return /active payment is already in progress/i.test(like.message ?? '');
}

function stringFromRaw(raw: Record<string, unknown> | null | undefined, key: string): string | null {
  const value = raw?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

export function recoveryFromStartError(
  error: unknown,
): ActivePaymentTransactionRef | null {
  if (!isPaymentInProgressError(error)) {
    return null;
  }

  const like = asErrorLike(error);
  const raw = like?.raw ?? null;
  const id =
    (typeof like?.paymentTransactionId === 'string' && like.paymentTransactionId.trim()) ||
    stringFromRaw(raw, 'payment_transaction_id') ||
    stringFromRaw(raw, 'paymentTransactionId');

  if (!id) {
    return null;
  }

  return {
    id,
    status:
      (typeof like?.paymentTransactionStatus === 'string' && like.paymentTransactionStatus) ||
      stringFromRaw(raw, 'payment_transaction_status') ||
      stringFromRaw(raw, 'status') ||
      'processing',
    provider:
      (typeof like?.provider === 'string' && like.provider) ||
      stringFromRaw(raw, 'provider'),
  };
}

export function paymentInProgressCustomerMessage(): string {
  return 'A payment request is already in progress for this order.';
}
