import type { OrderProgress, ReceivingChoiceSnapshot } from '../models/types';

/**
 * Negative terminal order.status values.
 * Mirrors OrderStatus::isTerminal minus Completed, plus RefundPending
 * (OrderLifecycleEngine::customerMayCancel also blocks RefundPending).
 */
export const NEGATIVE_TERMINAL_ORDER_STATUSES = new Set([
  'cancelled',
  'refunded',
  'refund_pending',
]);

/** Successful handover / closure. Distinct from cancelled / refunded. */
export const SUCCESS_TERMINAL_ORDER_STATUSES = new Set([
  'delivered',
  'completed',
]);

/**
 * Customer cancel CTA is never shown for these order.status values.
 * Mirrors OrderLifecycleEngine::customerMayCancel + OrderStatus transitions:
 * Shipped/Delivered cannot cancel; Completed/Cancelled/Refunded are terminal;
 * RefundPending is not cancellable.
 */
export const NEVER_CANCEL_ORDER_STATUSES = new Set([
  'shipped',
  'delivered',
  'completed',
  'cancelled',
  'refund_pending',
  'refunded',
]);

/**
 * Statuses where the customer cancel CTA may be offered when the server
 * omits `can_cancel`. Paid/confirmed/processing remain cancellable in domain
 * until fulfillment advances past processing.
 */
export const CUSTOMER_MAY_CANCEL_FALLBACK_STATUSES = new Set([
  'pending',
  'pending_payment',
  'paid',
  'confirmed',
  'processing',
]);

/** Backend progress keys that mean handover already happened. */
export const SUCCESS_TERMINAL_PROGRESS_KEYS = new Set([
  'DELIVERED',
  'DELIVERED_TO_AGENT',
]);

export function normalizeLifecycleStatus(
  value: string | null | undefined,
): string {
  return value?.trim().toLowerCase() ?? '';
}

export function isNegativeTerminalOrderStatus(
  status: string | null | undefined,
): boolean {
  return NEGATIVE_TERMINAL_ORDER_STATUSES.has(normalizeLifecycleStatus(status));
}

export function isSuccessTerminalOrderStatus(
  status: string | null | undefined,
): boolean {
  return SUCCESS_TERMINAL_ORDER_STATUSES.has(normalizeLifecycleStatus(status));
}

export function isSuccessTerminalProgress(
  progress?: OrderProgress | null,
): boolean {
  const key = progress?.currentKey?.trim() ?? '';
  return SUCCESS_TERMINAL_PROGRESS_KEYS.has(key);
}

/**
 * Authoritative success-terminal presentation: backend order.status
 * delivered/completed, or backend progress already at a delivered key.
 * Does not invent a client-side delivered/completed order.status.
 */
export function isSuccessTerminalLifecycle(input: {
  orderStatus: string | null | undefined;
  progress?: OrderProgress | null;
}): boolean {
  return (
    isSuccessTerminalOrderStatus(input.orderStatus) ||
    isSuccessTerminalProgress(input.progress)
  );
}

export function shouldOfferReceivingChoice(
  choice: ReceivingChoiceSnapshot | null | undefined,
): boolean {
  if (!choice) return false;
  return choice.eligible && choice.canSelect && !choice.selectedMethod;
}

/**
 * Selector visibility. Backend snapshot is authoritative for eligibility;
 * terminal order/progress never keeps a receiving action on screen.
 */
export function shouldShowReceivingSelector(
  choice: ReceivingChoiceSnapshot | null | undefined,
  orderStatus?: string | null,
  progress?: OrderProgress | null,
): boolean {
  if (isNegativeTerminalOrderStatus(orderStatus)) return false;
  if (isSuccessTerminalLifecycle({ orderStatus, progress })) return false;
  return shouldOfferReceivingChoice(choice);
}

/**
 * Cancel CTA visibility.
 *
 * Customer order resources do not currently expose `can_cancel`.
 * Domain authority is OrderLifecycleEngine::customerMayCancel.
 *
 * Floor: never show Cancel on shipped / delivered / completed / cancelled /
 * refund_pending / refunded, or when progress is already delivered.
 * Prefer explicit server `can_cancel` when present for remaining statuses.
 * Fallback: unpaid + paid / confirmed / processing.
 */
export function shouldOfferCancel(order: {
  status: string | null;
  canCancel: boolean | null;
  progress?: OrderProgress | null;
}): boolean {
  if (isNeverCancellableOrderStatus(order.status)) {
    return false;
  }
  if (isSuccessTerminalProgress(order.progress)) {
    return false;
  }
  if (typeof order.canCancel === 'boolean') {
    return order.canCancel;
  }
  return CUSTOMER_MAY_CANCEL_FALLBACK_STATUSES.has(
    normalizeLifecycleStatus(order.status),
  );
}

export function isNeverCancellableOrderStatus(
  status: string | null | undefined,
): boolean {
  return NEVER_CANCEL_ORDER_STATUSES.has(normalizeLifecycleStatus(status));
}

export function isPendingReceivingPresentationKey(key: string): boolean {
  return (
    key === 'choice_required' ||
    key === 'self_pickup' ||
    key === 'negotiated_delivery'
  );
}
