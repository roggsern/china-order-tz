import type { PaymentTransaction, PaymentTransactionStatus } from "@/lib/payments/types";
import { PAYMENT_TRANSACTION_STATUS } from "@/lib/payments/types";
import { publishPaymentConfirmation } from "@/lib/admin/server/order-event-hub";

declare global {
  var __chinaOrderTzPaymentTransactions: Map<string, PaymentTransaction> | undefined;
  var __chinaOrderTzPaymentCheckoutIndex: Map<string, string> | undefined;
  var __chinaOrderTzPaymentMerchantIndex: Map<string, string> | undefined;
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

function getMerchantIndex(): Map<string, string> {
  if (!globalThis.__chinaOrderTzPaymentMerchantIndex) {
    globalThis.__chinaOrderTzPaymentMerchantIndex = new Map();
  }
  return globalThis.__chinaOrderTzPaymentMerchantIndex;
}

export function saveTransaction(transaction: PaymentTransaction): void {
  getStore().set(transaction.transactionId, transaction);
  if (transaction.checkoutRequestId) {
    getCheckoutIndex().set(transaction.checkoutRequestId, transaction.transactionId);
  }
  if (transaction.merchantRequestId) {
    getMerchantIndex().set(transaction.merchantRequestId, transaction.transactionId);
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

export function getTransactionByMerchantRequestId(
  merchantRequestId: string,
): PaymentTransaction | null {
  const transactionId = getMerchantIndex().get(merchantRequestId);
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

  if (
    patch.status === PAYMENT_TRANSACTION_STATUS.PAID ||
    patch.status === PAYMENT_TRANSACTION_STATUS.FAILED
  ) {
    void publishPaymentConfirmation({
      orderId: updated.orderId,
      outcome: patch.status === PAYMENT_TRANSACTION_STATUS.PAID ? "paid" : "failed",
      paymentReference: updated.paymentReference,
      paymentTransactionId: updated.transactionId,
      failureReason: patch.failureReason ?? null,
      transaction: {
        transactionId: updated.transactionId,
        amount: updated.amount,
        phone: updated.phone,
        checkoutRequestId: updated.checkoutRequestId ?? updated.transactionId,
        merchantRequestId: updated.merchantRequestId,
      },
    });
  }

  return updated;
}
