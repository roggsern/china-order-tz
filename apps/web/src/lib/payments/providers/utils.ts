import { TEST_STK_AUTO_COMPLETE_MS } from "@/lib/payments/config";
import {
  getTransaction,
  updateTransactionStatus,
} from "@/lib/payments/transaction-store";
import type { PaymentProviderCode } from "@/lib/payments/providers/types";
import { PAYMENT_TRANSACTION_STATUS } from "@/lib/payments/types";
import type {
  PaymentMode,
  PaymentTransaction,
  PaymentTransactionStatus,
  VerifyPaymentResult,
} from "@/lib/payments/types";

export function generateTransactionId(): string {
  return `txn_${crypto.randomUUID().replace(/-/g, "")}`;
}

export function generateProviderReference(provider: PaymentProviderCode): string {
  const prefix = provider.toUpperCase();
  return `${prefix}-${Date.now().toString(36).toUpperCase()}`;
}

export function scheduleMockAutoComplete(
  transactionId: string,
  paymentReference: string,
): void {
  setTimeout(() => {
    const current = getTransaction(transactionId);
    if (!current || current.status !== PAYMENT_TRANSACTION_STATUS.PROCESSING) {
      return;
    }

    updateTransactionStatus(transactionId, {
      status: PAYMENT_TRANSACTION_STATUS.PAID,
      paymentReference,
      failureReason: null,
    });
  }, TEST_STK_AUTO_COMPLETE_MS);
}

export function toVerifyResult(transaction: PaymentTransaction): VerifyPaymentResult {
  const message =
    transaction.status === PAYMENT_TRANSACTION_STATUS.PAID
      ? "Payment confirmed successfully."
      : transaction.status === PAYMENT_TRANSACTION_STATUS.FAILED
        ? transaction.failureReason ?? "Payment failed."
        : transaction.status === PAYMENT_TRANSACTION_STATUS.PROCESSING
          ? "Payment request sent. Complete the prompt to confirm."
          : "Payment is pending.";

  return {
    transactionId: transaction.transactionId,
    orderId: transaction.orderId,
    orderNumber: transaction.orderNumber,
    status: transaction.status,
    paymentReference: transaction.paymentReference,
    amount: transaction.amount,
    message,
    mode: transaction.mode,
    provider: transaction.provider,
  };
}

export function buildInitiateResponse(
  transaction: PaymentTransaction,
  message: string,
): {
  success: boolean;
  transactionId: string;
  message: string;
  status: PaymentTransactionStatus;
  checkoutRequestId: string | null;
  mode: PaymentMode;
  provider: PaymentProviderCode;
} {
  return {
    success: transaction.status !== PAYMENT_TRANSACTION_STATUS.FAILED,
    transactionId: transaction.transactionId,
    message,
    status: transaction.status,
    checkoutRequestId: transaction.checkoutRequestId,
    mode: transaction.mode,
    provider: transaction.provider,
  };
}
