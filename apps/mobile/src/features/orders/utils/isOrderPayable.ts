const TERMINAL_UNPAYABLE_ORDER_STATUSES = new Set([
  'cancelled',
  'refunded',
  'refund_pending',
]);

/**
 * Payable detection from server-provided order state only.
 *
 * Prefer backend `can_pay` when present.
 * Cancelled / refunded / refund_pending never become payable from a stale
 * payment transaction or a contradictory can_pay flag.
 * Older fixtures that omit `can_pay` fall back to pending / pending_payment.
 * Never invent eligibility from age, local storage, or transaction status.
 */
export function isOrderPayableFromServer(order: {
  status: string | null;
  canPay?: boolean | null;
  paymentStatus?: string | null;
}): boolean {
  const status = order.status ?? '';

  if (
    TERMINAL_UNPAYABLE_ORDER_STATUSES.has(status) ||
    order.paymentStatus === 'refunded'
  ) {
    return false;
  }

  if (typeof order.canPay === 'boolean') {
    return order.canPay;
  }

  if (order.paymentStatus === 'paid') {
    return false;
  }

  return status === 'pending' || status === 'pending_payment';
}
