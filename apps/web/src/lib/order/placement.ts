import type { Order } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { PAYMENT_METHOD_CODES } from "@/lib/types/payment";

/** Orders with failed payment stay on the payment step for retry. */
export function shouldRedirectToOrderSuccess(order: Order): boolean {
  if (order.paymentStatus === PAYMENT_STATUS.FAILED) {
    return false;
  }

  if (
    order.paymentMethod === PAYMENT_METHOD_CODES.MPESA &&
    order.paymentStatus === PAYMENT_STATUS.PENDING
  ) {
    return false;
  }

  return true;
}

export function isOrderPaymentFailed(order: Order): boolean {
  return order.paymentStatus === PAYMENT_STATUS.FAILED;
}

export function isOrderPaymentPaid(order: Order): boolean {
  return order.paymentStatus === PAYMENT_STATUS.PAID;
}

export function isOrderPaymentPending(order: Order): boolean {
  return (
    order.paymentStatus === PAYMENT_STATUS.PENDING ||
    order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT
  );
}
