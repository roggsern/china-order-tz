import type { PaymentTransaction, PaymentTransactionStatus } from "@/lib/payment/server/types";

declare global {
  var __chinaOrderTzPaymentTransactions: Map<string, PaymentTransaction> | undefined;
  var __chinaOrderTzPaymentCheckoutIndex: Map<string, string> | undefined;
}

function getStore(): Map<string, PaymentTransaction> {
  if (!globalThis.__chinaOrderTzPaymentTransactions) {
    globalThis.__chinaOrderTzPaymentTransactions = new Map();
  }
  return globalThis.__chinaOrderTzPaymentTransactions;
}

function getCheckoutIndex(): Map<string, string> {
  if (!globalThis.__chinaOrderTzPaymentCheckoutIndex) {
    globalThis.__chinaOrderTzPaymentCheckoutIndex = new Map();
  }
  return globalThis.__chinaOrderTzPaymentCheckoutIndex;
}

export function saveTransaction(transaction: PaymentTransaction): void {
  getStore().set(transaction.transactionId, transaction);
  if (transaction.checkoutRequestId) {
    getCheckoutIndex().set(transaction.checkoutRequestId, transaction.transactionId);
  }
}

export function getTransaction(transactionId: string): PaymentTransaction | null {
  return getStore().get(transactionId) ?? null;
}

export function getTransactionByCheckoutRequestId(
  checkoutRequestId: string,
): PaymentTransaction | null {
  const transactionId = getCheckoutIndex().get(checkoutRequestId);
  if (!transactionId) {
    return null;
  }
  return getTransaction(transactionId);
}

export function updateTransactionStatus(
  transactionId: string,
  patch: {
    status: PaymentTransactionStatus;
    paymentReference?: string | null;
    failureReason?: string | null;
  },
): PaymentTransaction | null {
  const existing = getTransaction(transactionId);
  if (!existing) {
    return null;
  }

  const updated: PaymentTransaction = {
    ...existing,
    status: patch.status,
    paymentReference:
      patch.paymentReference !== undefined ? patch.paymentReference : existing.paymentReference,
    failureReason:
      patch.failureReason !== undefined ? patch.failureReason : existing.failureReason,
    updatedAt: new Date().toISOString(),
  };

  saveTransaction(updated);
  return updated;
}
