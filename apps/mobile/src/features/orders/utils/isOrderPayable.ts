/**
 * Payable detection from server-provided order status only.
 * Aligns with OrderStatus::isPayable() (pending / pending_payment).
 * Never invents eligibility from client lifecycle rules.
 */
export function isOrderPayableFromServer(order: {
  status: string | null;
}): boolean {
  return order.status === 'pending' || order.status === 'pending_payment';
}
