import type { Order } from "@/lib/types/order";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";
import { isGatewayPaymentMethod } from "@/lib/payment/payment-outcome";

/**
 * Whether the customer has finished the payment step and may leave checkout.
 *
 * Orders created at "Continue to Payment" arrive with paymentMethod=null and must
 * stay on /checkout/payment until the customer chooses a method and completes it.
 */
export function shouldRedirectToOrderSuccess(order: Order): boolean {
  if (order.paymentStatus === PAYMENT_STATUS.FAILED) {
    return false;
  }

  // Still waiting for the customer to pick NMB / M-Pesa / COD / Bank Transfer.
  if (!order.paymentMethod) {
    return false;
  }

  // Paid via gateway — confirmation screen is appropriate.
  if (order.paymentStatus === PAYMENT_STATUS.PAID) {
    return true;
  }

  // COD: accepted as place-order confirmation (payable on delivery).
  if (order.paymentMethod === PAYMENT_METHOD_CODES.COD) {
    return true;
  }

  // Bank transfer: order placed with pending_payment + instructions.
  if (
    order.paymentMethod === PAYMENT_METHOD_CODES.BANK_TRANSFER &&
    (order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT ||
      order.paymentStatus === PAYMENT_STATUS.PENDING)
  ) {
    return true;
  }

  // Gateway methods still awaiting STK / hosted checkout result.
  if (
    isGatewayPaymentMethod(order.paymentMethod) &&
    (order.paymentStatus === PAYMENT_STATUS.PENDING ||
      order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT)
  ) {
    return false;
  }

  return false;
}

/** True when a backend-confirmed order still needs method selection / payment. */
export function isAwaitingPaymentSelection(order: Order | null | undefined): boolean {
  if (!order) {
    return false;
  }

  return !order.paymentMethod && order.paymentStatus !== PAYMENT_STATUS.FAILED;
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
