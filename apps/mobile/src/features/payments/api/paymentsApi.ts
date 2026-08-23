import { ApiError } from '@/src/core/errors';
import { apiClient } from '@/src/core/api';
import type {
  PaymentMethodsAvailability,
  PaymentOrder,
  PreparedPayment,
  PaymentTransaction,
  ReconcileNmbReturnInput,
  StartPaymentOptions,
} from '../models/types';
import {
  buildReconcileNmbPayload,
  buildStartPaymentPayload,
  mapPaymentMethods,
  mapPaymentOrder,
  mapPreparedPayment,
  mapPaymentTransaction,
} from '../utils/mapPayment';

/** GET /payments/methods */
export async function fetchPaymentMethods(): Promise<PaymentMethodsAvailability> {
  const response = await apiClient.get<unknown>('/payments/methods');
  return mapPaymentMethods(response.data);
}

/**
 * POST /orders/from-checkout/{checkoutSession}
 * Server creates the order — required before payments/start/{order}.
 */
export async function createOrderFromCheckoutSession(
  checkoutSessionId: string,
): Promise<PaymentOrder> {
  const response = await apiClient.post<unknown>(
    `/orders/from-checkout/${encodeURIComponent(checkoutSessionId)}`,
  );
  const order = mapPaymentOrder(response.data);
  if (!order.id.trim()) {
    throw new ApiError({
      message:
        'Order was created but the response was incomplete. Open My Orders and continue payment.',
      status: 500,
      code: 'server_error',
      requestId: typeof response.request_id === 'string' ? response.request_id : null,
      raw: response && typeof response === 'object' ? (response as never) : null,
    });
  }
  return order;
}

/** POST /payments/start/{order} */
export async function startPayment(
  orderId: string,
  providerOrOptions?: string | StartPaymentOptions | null,
): Promise<PaymentTransaction> {
  const response = await apiClient.post<unknown>(
    `/payments/start/${encodeURIComponent(orderId)}`,
    buildStartPaymentPayload(providerOrOptions),
  );
  return mapPaymentTransaction(response.data);
}

/**
 * POST /orders/{order}/payments — prepares a Payment row (Pay at Office cash).
 * Does not create a PaymentTransaction and does not mark the order paid.
 */
export async function prepareOrderPayment(
  orderId: string,
  paymentMethod: string,
): Promise<PreparedPayment> {
  const response = await apiClient.post<unknown>(
    `/orders/${encodeURIComponent(orderId)}/payments`,
    { payment_method: paymentMethod },
  );
  const prepared = mapPreparedPayment(response.data);
  if (!prepared.id.trim()) {
    throw new ApiError({
      message: 'Payment could not be prepared. Please try again.',
      status: 500,
      code: 'server_error',
      requestId: typeof response.request_id === 'string' ? response.request_id : null,
      raw: response && typeof response === 'object' ? (response as never) : null,
    });
  }
  return prepared;
}

/** GET /payments/{paymentTransaction} */
export async function fetchPaymentTransaction(
  transactionId: string,
): Promise<PaymentTransaction> {
  const response = await apiClient.get<unknown>(
    `/payments/${encodeURIComponent(transactionId)}`,
  );
  return mapPaymentTransaction(response.data);
}

/** POST /payments/{paymentTransaction}/refresh */
export async function refreshPaymentTransaction(
  transactionId: string,
): Promise<PaymentTransaction> {
  const response = await apiClient.post<unknown>(
    `/payments/${encodeURIComponent(transactionId)}/refresh`,
  );
  return mapPaymentTransaction(response.data);
}

/** POST /payments/{paymentTransaction}/nmb/checkout-session */
export async function retryNmbCheckoutSession(
  transactionId: string,
): Promise<PaymentTransaction> {
  const response = await apiClient.post<unknown>(
    `/payments/${encodeURIComponent(transactionId)}/nmb/checkout-session`,
  );
  return mapPaymentTransaction(response.data);
}

/** GET /payments/return-context */
export async function resolvePaymentReturnContext(input: {
  orderId?: string | null;
  merchantReference?: string | null;
}): Promise<PaymentTransaction> {
  const response = await apiClient.get<unknown>('/payments/return-context', {
    order_id: input.orderId ?? undefined,
    merchant_reference: input.merchantReference ?? undefined,
  });
  return mapPaymentTransaction(response.data);
}

/**
 * POST /payments/nmb/return-reconcile — public proof-based reconcile.
 * Pass token null to omit Authorization (Contract: unauthenticated).
 */
export async function reconcileNmbBrowserReturn(
  input: ReconcileNmbReturnInput,
): Promise<PaymentTransaction> {
  const response = await apiClient.post<unknown>(
    '/payments/nmb/return-reconcile',
    buildReconcileNmbPayload(input),
    null,
  );
  return mapPaymentTransaction(response.data);
}
