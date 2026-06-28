import type { OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import type { PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";
import { GATEWAY_PAYMENT_METHODS } from "@/lib/payment/constants";

export function isGatewayPaymentMethod(
  method: PaymentMethodCode | null | undefined,
): method is PaymentMethodCode {
  if (!method) {
    return false;
  }
  return (GATEWAY_PAYMENT_METHODS as readonly string[]).includes(method);
}

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
    case PAYMENT_METHOD_CODES.NMB:
    case PAYMENT_METHOD_CODES.SELCOM:
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
    const fulfillmentStatuses: OrderStatus[] = [
      ORDER_STATUS.CONFIRMED,
      ORDER_STATUS.PROCESSING,
      ORDER_STATUS.PACKED,
      ORDER_STATUS.SHIPPED,
      ORDER_STATUS.IN_TRANSIT,
      ORDER_STATUS.DELIVERED,
    ];

    if (fulfillmentStatuses.includes(input.status)) {
      return { paymentStatus, status: input.status };
    }

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
