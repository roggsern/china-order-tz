import type { OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import type { PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";

export type PaymentOutcome = {
  paymentStatus: PaymentStatus;
  orderStatus: OrderStatus;
  paymentReference: string | null;
};

export function resolvePaymentOutcome(
  method: PaymentMethodCode,
  paymentReference?: string | null,
): PaymentOutcome {
  switch (method) {
    case PAYMENT_METHOD_CODES.MPESA:
      return {
        paymentStatus: PAYMENT_STATUS.PAID,
        orderStatus: ORDER_STATUS.CONFIRMED,
        paymentReference: paymentReference ?? null,
      };
    case PAYMENT_METHOD_CODES.COD:
      return {
        paymentStatus: PAYMENT_STATUS.PENDING,
        orderStatus: ORDER_STATUS.PENDING,
        paymentReference: null,
      };
    case PAYMENT_METHOD_CODES.BANK_TRANSFER:
      return {
        paymentStatus: PAYMENT_STATUS.PENDING_PAYMENT,
        orderStatus: ORDER_STATUS.PENDING_PAYMENT,
        paymentReference: null,
      };
    default:
      return {
        paymentStatus: PAYMENT_STATUS.PENDING,
        orderStatus: ORDER_STATUS.PENDING,
        paymentReference: null,
      };
  }
}

/** Keeps payment and order statuses aligned — no mixed states. */
export function reconcilePaymentStates(input: {
  paymentStatus: PaymentStatus;
  status: OrderStatus;
  paymentMethod?: PaymentMethodCode | null;
}): { paymentStatus: PaymentStatus; status: OrderStatus } {
  const { paymentStatus, paymentMethod } = input;

  if (paymentStatus === PAYMENT_STATUS.PAID) {
    return { paymentStatus, status: ORDER_STATUS.CONFIRMED };
  }

  if (paymentStatus === PAYMENT_STATUS.FAILED) {
    return { paymentStatus, status: ORDER_STATUS.PENDING };
  }

  if (paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT) {
    return { paymentStatus, status: ORDER_STATUS.PENDING_PAYMENT };
  }

  if (paymentStatus === PAYMENT_STATUS.CANCELLED || paymentStatus === PAYMENT_STATUS.REFUNDED) {
    return { paymentStatus, status: ORDER_STATUS.CANCELLED };
  }

  if (paymentMethod === PAYMENT_METHOD_CODES.COD) {
    return { paymentStatus: PAYMENT_STATUS.PENDING, status: ORDER_STATUS.PENDING };
  }

  if (paymentMethod === PAYMENT_METHOD_CODES.BANK_TRANSFER) {
    return { paymentStatus: PAYMENT_STATUS.PENDING_PAYMENT, status: ORDER_STATUS.PENDING_PAYMENT };
  }

  return { paymentStatus: PAYMENT_STATUS.PENDING, status: ORDER_STATUS.PENDING };
}

export function generateMpesaReference(): string {
  return `MPESA-SIM-${Date.now().toString(36).toUpperCase()}`;
}
