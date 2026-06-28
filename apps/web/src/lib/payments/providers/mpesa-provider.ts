import { getPaymentMode, isPaymentTestMode } from "@/lib/payments/config";
import {
  initiateSTKPush,
  normalizeMpesaPhone,
  shouldUseLiveStkPush,
  simulateSTKPush,
} from "@/lib/payments/mpesa";
import { processMpesaStkCallback, isValidMpesaCallbackPayload } from "@/lib/payments/mpesa-callback";
import { saveTransaction } from "@/lib/payments/transaction-store";
import type {
  PaymentCallbackResult,
  PaymentInitiateContext,
  PaymentProvider,
} from "@/lib/payments/providers/types";
import { PAYMENT_PROVIDER } from "@/lib/payments/providers/types";
import {
  buildInitiateResponse,
  generateTransactionId,
  scheduleMockAutoComplete,
  toVerifyResult,
} from "@/lib/payments/providers/utils";
import { getTransaction } from "@/lib/payments/transaction-store";
import type { NormalizedPaymentResponse } from "@/lib/payments/providers/types";
import { PAYMENT_TRANSACTION_STATUS } from "@/lib/payments/types";
import type { PaymentTransaction, VerifyPaymentResult } from "@/lib/payments/types";
import { generateTestMpesaReceipt } from "@/lib/payments/mpesa";

export class MpesaProvider implements PaymentProvider {
  readonly code = PAYMENT_PROVIDER.MPESA;

  canHandleCallback(payload: unknown): boolean {
    return isValidMpesaCallbackPayload(payload);
  }

  async initiatePayment(context: PaymentInitiateContext): Promise<NormalizedPaymentResponse> {
    const mode = getPaymentMode();
    const now = new Date().toISOString();
    const transactionId = generateTransactionId();
    const normalizedPhone = normalizeMpesaPhone(context.phone);

    if (!normalizedPhone || normalizedPhone.length < 12) {
      return {
        success: false,
        transactionId: null,
        status: PAYMENT_TRANSACTION_STATUS.FAILED,
        checkoutRequestId: null,
        message: "Enter a valid Tanzanian mobile number for M-Pesa.",
        mode,
        provider: this.code,
      };
    }

    const baseTransaction: PaymentTransaction = {
      transactionId,
      orderId: context.orderId,
      orderNumber: context.orderNumber,
      amount: context.amount,
      phone: normalizedPhone,
      status: PAYMENT_TRANSACTION_STATUS.PENDING,
      paymentReference: null,
      checkoutRequestId: null,
      merchantRequestId: null,
      provider: this.code,
      mode,
      failureReason: null,
      createdAt: now,
      updatedAt: now,
    };

    try {
      const stk = shouldUseLiveStkPush()
        ? await initiateSTKPush({
            phone: normalizedPhone,
            amount: context.amount,
            accountReference: context.accountReference ?? context.orderNumber,
            description: context.description ?? "CHINA ORDER TZ",
          })
        : await simulateSTKPush({
            phone: normalizedPhone,
            amount: context.amount,
            accountReference: context.accountReference ?? context.orderNumber,
          });

      if (stk.ResponseCode !== "0") {
        const failed: PaymentTransaction = {
          ...baseTransaction,
          status: PAYMENT_TRANSACTION_STATUS.FAILED,
          failureReason: stk.ResponseDescription || "STK Push request rejected.",
          merchantRequestId: stk.MerchantRequestID,
          checkoutRequestId: stk.CheckoutRequestID,
        };
        saveTransaction(failed);
        return {
          ...buildInitiateResponse(failed, failed.failureReason ?? "STK Push failed."),
          transactionId: failed.transactionId,
        };
      }

      const processing: PaymentTransaction = {
        ...baseTransaction,
        status: PAYMENT_TRANSACTION_STATUS.PROCESSING,
        merchantRequestId: stk.MerchantRequestID,
        checkoutRequestId: stk.CheckoutRequestID,
      };
      saveTransaction(processing);

      if (!shouldUseLiveStkPush()) {
        scheduleMockAutoComplete(transactionId, generateTestMpesaReceipt());
      }

      return {
        ...buildInitiateResponse(
          processing,
          isPaymentTestMode()
            ? "Test mode: STK Push simulated. Payment will auto-complete shortly."
            : stk.CustomerMessage || "STK Push sent. Check your phone to enter your M-Pesa PIN.",
        ),
        transactionId: processing.transactionId,
      };
    } catch (error) {
      const message =
        error instanceof Error ? error.message : "Unable to initiate M-Pesa payment.";
      const failed: PaymentTransaction = {
        ...baseTransaction,
        status: PAYMENT_TRANSACTION_STATUS.FAILED,
        failureReason: message,
      };
      saveTransaction(failed);
      return {
        ...buildInitiateResponse(failed, message),
        transactionId: failed.transactionId,
      };
    }
  }

  async handleCallback(payload: unknown): Promise<PaymentCallbackResult> {
    const result = await processMpesaStkCallback(payload as Parameters<typeof processMpesaStkCallback>[0]);
    return { ...result, provider: this.code };
  }

  verifyPayment(transactionId: string): VerifyPaymentResult | null {
    const transaction = getTransaction(transactionId);
    if (!transaction || transaction.provider !== this.code) {
      return null;
    }
    return toVerifyResult(transaction);
  }
}

export const mpesaProvider = new MpesaProvider();
