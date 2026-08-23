/**
 * Pay Now eligibility. Backend `can_pay` is authoritative when present.
 * Never hides Pay Now because of age, sessionStorage, or a missing local
 * paymentTransactionId.
 */
export function isCustomerOrderPayable(order: {
  canPay?: boolean | null;
  status: string;
  paymentStatus?: string | null;
  paidAt?: string | null;
}): boolean {
  if (typeof order.canPay === "boolean") {
    return order.canPay;
  }

  if (order.paidAt) {
    return false;
  }

  if (
    order.status === "cancelled" ||
    order.status === "refunded" ||
    order.status === "refund_pending"
  ) {
    return false;
  }

  if (order.paymentStatus === "paid" || order.paymentStatus === "refunded") {
    return false;
  }

  return order.status === "pending" || order.status === "pending_payment";
}
