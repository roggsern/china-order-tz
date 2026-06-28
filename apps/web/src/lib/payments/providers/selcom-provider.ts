import { getPaymentMode, isPaymentTestMode } from "@/lib/payments/config";
import { saveTransaction, updateTransactionStatus } from "@/lib/payments/transaction-store";
import { getTransaction } from "@/lib/payments/transaction-store";
import type {
  MockProviderCallbackPayload,
  NormalizedPaymentResponse,
  PaymentCallbackResult,
  PaymentInitiateContext,
  PaymentProvider,
} from "@/lib/payments/providers/types";
import { PAYMENT_PROVIDER } from "@/lib/payments/providers/types";
import {
  buildInitiateResponse,
  generateProviderReference,
  generateTransactionId,
  scheduleMockAutoComplete,
  toVerifyResult,
} from "@/lib/payments/providers/utils";
import { PAYMENT_TRANSACTION_STATUS } from "@/lib/payments/types";
import type { PaymentTransaction, VerifyPaymentResult } from "@/lib/payments/types";

function isMockCallback(payload: unknown, provider: string): payload is MockProviderCallbackPayload {
  if (!payload || typeof payload !== "object") {
    return false;
  }
  const body = payload as MockProviderCallbackPayload;
  return body.provider === provider && typeof body.transactionId === "string";
}

/**
 * Selcom payment provider — mock implementation.
 * Simulates mobile checkout with auto-complete in test mode.
 */
export class SelcomProvider implements PaymentProvider {
  readonly code = PAYMENT_PROVIDER.SELCOM;

  canHandleCallback(payload: unknown): boolean {
    return isMockCallback(payload, PAYMENT_PROVIDER.SELCOM);
  }

  async initiatePayment(context: PaymentInitiateContext): Promise<NormalizedPaymentResponse> {
    const mode = getPaymentMode();
    const now = new Date().toISOString();
    const transactionId = generateTransactionId();
    const checkoutRequestId = `selcom_${transactionId.slice(4)}`;
    const reference = generateProviderReference(this.code);

    const transaction: PaymentTransaction = {
      transactionId,
      orderId: context.orderId,
      orderNumber: context.orderNumber,
      amount: context.amount,
      phone: context.phone,
      status: PAYMENT_TRANSACTION_STATUS.PROCESSING,
      paymentReference: null,
      checkoutRequestId,
      merchantRequestId: checkoutRequestId,
      provider: this.code,
      mode,
      failureReason: null,
      createdAt: now,
      updatedAt: now,
    };

    saveTransaction(transaction);

    if (isPaymentTestMode() || mode === "test") {
      scheduleMockAutoComplete(transactionId, reference);
    }

    return {
      ...buildInitiateResponse(
        transaction,
        isPaymentTestMode()
          ? "Selcom test mode: checkout simulated. Auto-completing shortly."
          : "Selcom checkout initiated. Complete payment on the Selcom prompt.",
      ),
      transactionId,
    };
  }

  async handleCallback(payload: unknown): Promise<PaymentCallbackResult> {
    if (!this.canHandleCallback(payload)) {
      return { handled: false, message: "Not a Selcom callback payload.", provider: this.code };
    }

    const body = payload as MockProviderCallbackPayload;
    const transaction = getTransaction(body.transactionId);

    if (!transaction || transaction.provider !== this.code) {
      return {
        handled: false,
        message: "Selcom transaction not found.",
        provider: this.code,
      };
    }

    if (transaction.status === PAYMENT_TRANSACTION_STATUS.PAID) {
      return {
        handled: true,
        duplicate: true,
        transactionId: transaction.transactionId,
        orderId: transaction.orderId,
        status: transaction.status,
        paymentReference: transaction.paymentReference,
        message: "Duplicate Selcom callback ignored.",
        provider: this.code,
      };
    }

    const isPaid = body.status === "paid";
    updateTransactionStatus(transaction.transactionId, {
      status: isPaid ? PAYMENT_TRANSACTION_STATUS.PAID : PAYMENT_TRANSACTION_STATUS.FAILED,
      paymentReference: isPaid ? (body.reference ?? generateProviderReference(this.code)) : null,
      failureReason: isPaid ? null : body.message ?? "Selcom payment failed.",
    });

    return {
      handled: true,
      transactionId: transaction.transactionId,
      orderId: transaction.orderId,
      status: isPaid ? PAYMENT_TRANSACTION_STATUS.PAID : PAYMENT_TRANSACTION_STATUS.FAILED,
      paymentReference: isPaid ? (body.reference ?? null) : null,
      message: body.message ?? (isPaid ? "Selcom payment confirmed." : "Selcom payment failed."),
      provider: this.code,
    };
  }

  verifyPayment(transactionId: string): VerifyPaymentResult | null {
    const transaction = getTransaction(transactionId);
    if (!transaction || transaction.provider !== this.code) {
      return null;
    }
    return toVerifyResult(transaction);
  }
}

export const selcomProvider = new SelcomProvider();
