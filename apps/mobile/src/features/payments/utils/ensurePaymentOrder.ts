import { createOrderFromCheckoutSession } from '../api/paymentsApi';
import type { PaymentOrder } from '../models/types';

export async function ensurePaymentOrder(input: {
  orderId?: string | null;
  checkoutSessionId?: string | null;
  existingOrder?: PaymentOrder | null;
}): Promise<PaymentOrder> {
  if (input.existingOrder?.id.trim()) {
    return input.existingOrder;
  }

  if (input.orderId?.trim()) {
    return {
      id: input.orderId.trim(),
      orderNumber: null,
      status: null,
      currency: 'TZS',
      grandTotal: null,
      checkoutSessionId: input.checkoutSessionId ?? null,
    };
  }

  if (input.checkoutSessionId?.trim()) {
    return createOrderFromCheckoutSession(input.checkoutSessionId.trim());
  }

  throw new Error('Checkout session or order is required to start payment.');
}
