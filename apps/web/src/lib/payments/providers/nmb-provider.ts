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
 * NMB Bank payment provider — mock implementation.
 * Simulates push-to-pay flow with auto-complete in test mode.
 */
export class NmbProvider implements PaymentProvider {
  readonly code = PAYMENT_PROVIDER.NMB;

  canHandleCallback(payload: unknown): boolean {
    return isMockCallback(payload, PAYMENT_PROVIDER.NMB);
  }

  async initiatePayment(context: PaymentInitiateContext): Promise<NormalizedPaymentResponse> {
    const mode = getPaymentMode();
    const now = new Date().toISOString();
    const transactionId = generateTransactionId();
    const checkoutRequestId = `nmb_${transactionId.slice(4)}`;
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
          ? "NMB test mode: payment request simulated. Auto-completing shortly."
          : "NMB payment initiated. Approve the request in your NMB mobile app.",
      ),
      transactionId,
    };
  }

  async handleCallback(payload: unknown): Promise<PaymentCallbackResult> {
    if (!this.canHandleCallback(payload)) {
      return { handled: false, message: "Not an NMB callback payload.", provider: this.code };
    }

    const body = payload as MockProviderCallbackPayload;
    const transaction = getTransaction(body.transactionId);

    if (!transaction || transaction.provider !== this.code) {
      return {
        handled: false,
        message: "NMB transaction not found.",
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
        message: "Duplicate NMB callback ignored.",
        provider: this.code,
      };
    }

    const isPaid = body.status === "paid";
    updateTransactionStatus(transaction.transactionId, {
      status: isPaid ? PAYMENT_TRANSACTION_STATUS.PAID : PAYMENT_TRANSACTION_STATUS.FAILED,
      paymentReference: isPaid ? (body.reference ?? generateProviderReference(this.code)) : null,
      failureReason: isPaid ? null : body.message ?? "NMB payment failed.",
    });

    return {
      handled: true,
      transactionId: transaction.transactionId,
      orderId: transaction.orderId,
      status: isPaid ? PAYMENT_TRANSACTION_STATUS.PAID : PAYMENT_TRANSACTION_STATUS.FAILED,
      paymentReference: isPaid ? (body.reference ?? null) : null,
      message: body.message ?? (isPaid ? "NMB payment confirmed." : "NMB payment failed."),
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

export const nmbProvider = new NmbProvider();
