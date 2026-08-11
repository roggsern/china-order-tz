import { apiClient } from '@/src/core/api';
import type {
  PaymentMethodsAvailability,
  PaymentOrder,
  PaymentTransaction,
  ReconcileNmbReturnInput,
} from '../models/types';
import {
  buildReconcileNmbPayload,
  buildStartPaymentPayload,
  mapPaymentMethods,
  mapPaymentOrder,
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
  return mapPaymentOrder(response.data);
}

/** POST /payments/start/{order} */
export async function startPayment(
  orderId: string,
  provider?: string | null,
): Promise<PaymentTransaction> {
  const response = await apiClient.post<unknown>(
    `/payments/start/${encodeURIComponent(orderId)}`,
    buildStartPaymentPayload(provider),
  );
  return mapPaymentTransaction(response.data);
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
