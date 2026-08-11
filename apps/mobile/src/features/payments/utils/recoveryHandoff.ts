import { pendingCheckoutContextStorage } from '@/src/features/checkout/storage/pendingCheckoutContextStorage';
import { useAuthStore } from '@/src/core/auth/authStore';
import { pendingPaymentContextStorage } from '../storage/pendingPaymentContextStorage';

/**
 * When payment phase starts with recoverable order/txn ids,
 * ownership moves from checkout recovery → payment recovery.
 */
export async function handOffCheckoutToPayment(input: {
  orderId: string | null;
  paymentTransactionId: string | null;
  merchantReference: string | null;
  successIndicator: string | null;
  checkoutSessionId: string | null;
  resultIndicator?: string | null;
}): Promise<void> {
  const userId = useAuthStore.getState().user?.id ?? null;

  await pendingPaymentContextStorage.save({
    userId,
    orderId: input.orderId,
    paymentTransactionId: input.paymentTransactionId,
    merchantReference: input.merchantReference,
    successIndicator: input.successIndicator,
    resultIndicator: input.resultIndicator ?? null,
    checkoutSessionId: input.checkoutSessionId,
  });

  // Payment context is now authoritative for resume — drop checkout prompt.
  if (input.orderId || input.paymentTransactionId) {
    await pendingCheckoutContextStorage.clear();
  }
}

export async function clearPaymentAndCheckoutContexts(): Promise<void> {
  await pendingPaymentContextStorage.clear();
  await pendingCheckoutContextStorage.clear();
}
