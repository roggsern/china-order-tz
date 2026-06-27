import { getPaymentMode, isPaymentTestMode } from "@/lib/payment/server/config";
import {
  canUseLiveMpesa,
  createTestStkPushResponse,
  initiateMpesaStkPush,
  normalizeMpesaPhone,
} from "@/lib/payment/server/mpesa";
import {
  getTransaction,
  getTransactionByCheckoutRequestId,
  saveTransaction,
  updateTransactionStatus,
} from "@/lib/payment/server/transaction-store";
import type {
  InitiatePaymentInput,
  InitiatePaymentResult,
  MpesaStkCallbackPayload,
  PaymentTransaction,
  SimulatePaymentResult,
  VerifyPaymentResult,
} from "@/lib/payment/server/types";
import { logServerPaymentEvent } from "@/lib/payment/server/payment-logger";

const TEST_AUTO_COMPLETE_MS = 2_500;

function generateTransactionId(): string {
  return `txn_${crypto.randomUUID().replace(/-/g, "")}`;
}

function generateTestReceipt(): string {
  return `MPESA-TEST-${Date.now().toString(36).toUpperCase()}`;
}

function scheduleTestAutoComplete(transactionId: string): void {
  setTimeout(() => {
    const current = getTransaction(transactionId);
    if (!current || current.status !== "pending") {
      return;
    }

    updateTransactionStatus(transactionId, {
      status: "paid",
      paymentReference: generateTestReceipt(),
      failureReason: null,
    });
  }, TEST_AUTO_COMPLETE_MS);
}

function toInitiateResult(transaction: PaymentTransaction, message: string): InitiatePaymentResult {
  return {
    success: transaction.status !== "failed",
    transactionId: transaction.transactionId,
    status: transaction.status,
    checkoutRequestId: transaction.checkoutRequestId,
    message,
    mode: transaction.mode,
  };
}

function toVerifyResult(transaction: PaymentTransaction): VerifyPaymentResult {
  const message =
    transaction.status === "paid"
      ? "Payment confirmed successfully."
      : transaction.status === "failed"
        ? transaction.failureReason ?? "Payment failed."
        : "Payment is still pending. Complete the STK prompt on your phone.";

  return {
    transactionId: transaction.transactionId,
    orderId: transaction.orderId,
    orderNumber: transaction.orderNumber,
    status: transaction.status,
    paymentReference: transaction.paymentReference,
    amount: transaction.amount,
    message,
    mode: transaction.mode,
  };
}

export class ServerPaymentGateway {
  async initiatePayment(input: InitiatePaymentInput): Promise<InitiatePaymentResult> {
    const mode = getPaymentMode();
    const now = new Date().toISOString();
    const transactionId = generateTransactionId();
    const normalizedPhone = normalizeMpesaPhone(input.phone);

    if (!normalizedPhone || normalizedPhone.length < 12) {
      return {
        success: false,
        transactionId: null,
        status: "failed",
        checkoutRequestId: null,
        message: "Enter a valid Tanzanian mobile number for M-Pesa.",
        mode,
      };
    }

    const baseTransaction: PaymentTransaction = {
      transactionId,
      orderId: input.orderId,
      orderNumber: input.orderNumber,
      amount: input.amount,
      phone: normalizedPhone,
      status: "pending",
      paymentReference: null,
      checkoutRequestId: null,
      merchantRequestId: null,
      provider: "mpesa",
      mode,
      failureReason: null,
      createdAt: now,
      updatedAt: now,
    };

    try {
      if (canUseLiveMpesa()) {
        const stk = await initiateMpesaStkPush({
          phone: normalizedPhone,
          amount: input.amount,
          accountReference: input.accountReference ?? input.orderNumber,
          description: input.description ?? "CHINA ORDER TZ",
        });

        if (stk.ResponseCode !== "0") {
          const failed: PaymentTransaction = {
            ...baseTransaction,
            status: "failed",
            failureReason: stk.ResponseDescription || "STK Push request rejected.",
            merchantRequestId: stk.MerchantRequestID,
            checkoutRequestId: stk.CheckoutRequestID,
          };
          saveTransaction(failed);
          return toInitiateResult(failed, failed.failureReason ?? "STK Push failed.");
        }

        const pending: PaymentTransaction = {
          ...baseTransaction,
          merchantRequestId: stk.MerchantRequestID,
          checkoutRequestId: stk.CheckoutRequestID,
        };
        saveTransaction(pending);
        return toInitiateResult(
          pending,
          stk.CustomerMessage || "STK Push sent. Check your phone to enter your M-Pesa PIN.",
        );
      }

      const stk = createTestStkPushResponse();
      const pending: PaymentTransaction = {
        ...baseTransaction,
        merchantRequestId: stk.MerchantRequestID,
        checkoutRequestId: stk.CheckoutRequestID,
      };
      saveTransaction(pending);
      scheduleTestAutoComplete(transactionId);

      return toInitiateResult(
        pending,
        isPaymentTestMode()
          ? "Test mode: STK Push simulated. Payment will auto-complete in a few seconds."
          : stk.CustomerMessage,
      );
    } catch (error) {
      const message =
        error instanceof Error ? error.message : "Unable to initiate M-Pesa payment.";
      const failed: PaymentTransaction = {
        ...baseTransaction,
        status: "failed",
        failureReason: message,
      };
      saveTransaction(failed);
      return toInitiateResult(failed, message);
    }
  }

  verifyPayment(transactionId: string): VerifyPaymentResult | null {
    const transaction = getTransaction(transactionId);
    if (!transaction) {
      return null;
    }

    return toVerifyResult(transaction);
  }

  handlePaymentCallback(payload: MpesaStkCallbackPayload): {
    handled: boolean;
    transactionId?: string;
    status?: PaymentTransaction["status"];
    message: string;
  } {
    const callback = payload.Body?.stkCallback;
    if (!callback?.CheckoutRequestID) {
      return { handled: false, message: "Invalid callback payload." };
    }

    const transaction = getTransactionByCheckoutRequestId(callback.CheckoutRequestID);
    if (!transaction) {
      return { handled: false, message: "Transaction not found for callback." };
    }

    if (callback.ResultCode === 0) {
      const receipt =
        callback.CallbackMetadata?.Item?.find((item) => item.Name === "MpesaReceiptNumber")
          ?.Value?.toString() ?? generateTestReceipt();

      updateTransactionStatus(transaction.transactionId, {
        status: "paid",
        paymentReference: receipt,
        failureReason: null,
      });

      return {
        handled: true,
        transactionId: transaction.transactionId,
        status: "paid",
        message: "Payment confirmed via callback.",
      };
    }

    updateTransactionStatus(transaction.transactionId, {
      status: "failed",
      failureReason: callback.ResultDesc ?? "Payment cancelled or failed.",
    });

    return {
      handled: true,
      transactionId: transaction.transactionId,
      status: "failed",
      message: callback.ResultDesc ?? "Payment failed.",
    };
  }

  /**
   * Instantly completes a payment in test mode — no STK Push, no real money.
   */
  simulatePayment(input: InitiatePaymentInput): SimulatePaymentResult {
    if (!isPaymentTestMode()) {
      throw new Error("Simulate payment is only available in test mode.");
    }

    const transactionId = generateTransactionId();
    const paymentReference = generateTestReceipt();
    const now = new Date().toISOString();
    const normalizedPhone = normalizeMpesaPhone(input.phone) || input.phone;

    const paid: PaymentTransaction = {
      transactionId,
      orderId: input.orderId,
      orderNumber: input.orderNumber,
      amount: input.amount,
      phone: normalizedPhone,
      status: "paid",
      paymentReference,
      checkoutRequestId: `SIM-CR-${transactionId.slice(-12).toUpperCase()}`,
      merchantRequestId: `SIM-MR-${transactionId.slice(-12).toUpperCase()}`,
      provider: "mpesa",
      mode: "test",
      failureReason: null,
      createdAt: now,
      updatedAt: now,
    };

    saveTransaction(paid);

    logServerPaymentEvent("simulate:completed", {
      transactionId,
      orderId: input.orderId,
      orderNumber: input.orderNumber,
      paymentReference,
      amount: input.amount,
    });

    return {
      success: true,
      transactionId,
      paymentReference,
      status: "paid",
      orderId: input.orderId,
      orderNumber: input.orderNumber,
      amount: input.amount,
      message: "Test payment simulated successfully. No real money was charged.",
      mode: "test",
    };
  }
}

export const serverPaymentGateway = new ServerPaymentGateway();
