import {
  reconcileNmbBrowserReturn,
  refreshPaymentTransaction,
  resolvePaymentReturnContext,
} from '../api/paymentsApi';
import type { PaymentTransaction } from '../models/types';
import { pendingPaymentContextStorage } from '../storage/pendingPaymentContextStorage';
import { parsePaymentReturnUrl } from './mapPayment';

export type HandlePaymentReturnInput = {
  /** Full deep-link / auth-session return URL, if available. */
  returnUrl?: string | null;
  /** Explicit URL query fields (cold-start Expo Router params). */
  resultIndicator?: string | null;
  orderId?: string | null;
  merchantReference?: string | null;
  paymentTransactionId?: string | null;
  /**
   * Optional injected storage for tests.
   * Defaults to SecureStore-backed pendingPaymentContextStorage.
   */
  storage?: typeof pendingPaymentContextStorage;
};

export type HandlePaymentReturnResult = {
  transaction: PaymentTransaction | null;
  orderId: string | null;
  reconciled: boolean;
  refreshed: boolean;
  /** True when URL/persisted context was insufficient to resolve a transaction. */
  incomplete: boolean;
};

function pick(
  ...values: (string | null | undefined)[]
): string | null {
  for (const value of values) {
    if (typeof value !== 'string' || value.trim() === '') continue;
    return value.trim();
  }
  return null;
}

/**
 * Shared payment-return handler.
 * Never marks paid from URL params. NMB reconcile runs only when NMB proof
 * fields are present. Other providers refresh authoritative transaction state.
 */
export async function handlePaymentReturn(
  input: HandlePaymentReturnInput = {},
): Promise<HandlePaymentReturnResult> {
  const storage = input.storage ?? pendingPaymentContextStorage;
  const persisted = await storage.readValid();
  const fromUrl = input.returnUrl?.trim()
    ? parsePaymentReturnUrl(input.returnUrl)
    : {
        resultIndicator: null,
        orderId: null,
        merchantReference: null,
        paymentTransactionId: null,
        embedsAuthToken: false,
      };

  let orderId = pick(
    input.orderId,
    fromUrl.orderId,
    persisted?.orderId,
  );
  let paymentTransactionId = pick(
    input.paymentTransactionId,
    fromUrl.paymentTransactionId,
    persisted?.paymentTransactionId,
  );
  let merchantReference = pick(
    input.merchantReference,
    fromUrl.merchantReference,
    persisted?.merchantReference,
  );
  const resultIndicator = pick(
    input.resultIndicator,
    fromUrl.resultIndicator,
    persisted?.resultIndicator,
  );
  let successIndicator = pick(persisted?.successIndicator);

  // Persist deep-link hints for auth-recovery resume (never proof of paid).
  const mergePatch: Parameters<typeof storage.merge>[0] = {};
  if (resultIndicator) mergePatch.resultIndicator = resultIndicator;
  if (orderId) mergePatch.orderId = orderId;
  if (paymentTransactionId) mergePatch.paymentTransactionId = paymentTransactionId;
  if (merchantReference) mergePatch.merchantReference = merchantReference;
  if (Object.keys(mergePatch).length > 0) {
    await storage.merge(mergePatch);
  }

  let transaction: PaymentTransaction | null = null;
  let reconciled = false;
  let refreshed = false;

  // Resolve txn via authenticated return-context when proof/ids are incomplete.
  if (!paymentTransactionId || !successIndicator || !merchantReference) {
    if (orderId || merchantReference) {
      try {
        transaction = await resolvePaymentReturnContext({
          orderId,
          merchantReference,
        });
        paymentTransactionId = pick(transaction.id, paymentTransactionId);
        orderId = pick(transaction.orderId, transaction.order?.id, orderId);
        merchantReference = pick(transaction.merchantReference, merchantReference);
        successIndicator = pick(transaction.successIndicator, successIndicator);
      } catch {
        // Continue with whatever we have; refresh may still work.
      }
    }
  }

  const canReconcile = Boolean(
    resultIndicator &&
      successIndicator &&
      merchantReference &&
      paymentTransactionId,
  );

  if (canReconcile) {
    try {
      transaction = await reconcileNmbBrowserReturn({
        paymentTransactionId: paymentTransactionId!,
        merchantReference: merchantReference!,
        successIndicator: successIndicator!,
        resultIndicator: resultIndicator!,
        orderId,
      });
      reconciled = true;
      paymentTransactionId = pick(transaction.id, paymentTransactionId);
      orderId = pick(transaction.orderId, transaction.order?.id, orderId);
    } catch {
      // Failed proof must not invent success — fall through to refresh.
    }
  }

  const txnId = pick(paymentTransactionId, transaction?.id);
  if (txnId) {
    try {
      transaction = await refreshPaymentTransaction(txnId);
      refreshed = true;
      orderId = pick(transaction.orderId, transaction.order?.id, orderId);
    } catch {
      // Leave transaction as reconcile/context result if refresh fails.
    }
  }

  if (transaction && isSuccessfulPaymentStatusSafe(transaction.status)) {
    await storage.clear();
  }

  return {
    transaction,
    orderId,
    reconciled,
    refreshed,
    incomplete: transaction == null,
  };
}

function isSuccessfulPaymentStatusSafe(status: string | null | undefined): boolean {
  return status === 'successful';
}

/** Wave 1 NMB entry — same shared handler, NMB reconcile only when proof fields exist. */
export const handleNmbPaymentReturn = handlePaymentReturn;
