import { logServerPaymentEvent } from "@/lib/payment/server/payment-logger";
import { publishPaymentConfirmation } from "@/lib/admin/server/order-event-hub";
import {
  getTransactionByCheckoutRequestId,
  getTransactionByMerchantRequestId,
  saveTransaction,
} from "@/lib/payments/transaction-store";
import type {
  MpesaCallbackResult,
  MpesaStkCallbackPayload,
  ParsedMpesaStkCallback,
  PaymentTransaction,
} from "@/lib/payments/types";
import { PAYMENT_TRANSACTION_STATUS } from "@/lib/payments/types";
import { generateTestMpesaReceipt } from "@/lib/payments/mpesa";

declare global {
  var __chinaOrderTzProcessedCheckoutRequestIds: Set<string> | undefined;
}

function getProcessedCheckoutRequestIds(): Set<string> {
  if (!globalThis.__chinaOrderTzProcessedCheckoutRequestIds) {
    globalThis.__chinaOrderTzProcessedCheckoutRequestIds = new Set();
  }
  return globalThis.__chinaOrderTzProcessedCheckoutRequestIds;
}

function markCheckoutRequestProcessed(checkoutRequestId: string): void {
  getProcessedCheckoutRequestIds().add(checkoutRequestId);
}

function isDuplicateCheckoutRequest(checkoutRequestId: string): boolean {
  return getProcessedCheckoutRequestIds().has(checkoutRequestId);
}

function readMetadataValue(
  items: Array<{ Name?: string; Value?: string | number }> | undefined,
  name: string,
): string | null {
  const entry = items?.find((item) => item.Name === name);
  if (entry?.Value === undefined || entry.Value === null) {
    return null;
  }
  return String(entry.Value);
}

function readMetadataAmount(
  items: Array<{ Name?: string; Value?: string | number }> | undefined,
): number | null {
  const raw = readMetadataValue(items, "Amount");
  if (!raw) {
    return null;
  }
  const amount = Number.parseFloat(raw);
  return Number.isFinite(amount) ? amount : null;
}

export function isValidMpesaCallbackPayload(
  payload: unknown,
): payload is MpesaStkCallbackPayload {
  if (!payload || typeof payload !== "object") {
    return false;
  }

  const body = (payload as MpesaStkCallbackPayload).Body;
  if (!body?.stkCallback) {
    return false;
  }

  const callback = body.stkCallback;
  return typeof callback.CheckoutRequestID === "string" && callback.CheckoutRequestID.length > 0;
}

export function parseStkCallbackPayload(payload: MpesaStkCallbackPayload): ParsedMpesaStkCallback | null {
  const callback = payload.Body?.stkCallback;
  if (!callback?.CheckoutRequestID) {
    return null;
  }

  const items = callback.CallbackMetadata?.Item;

  return {
    merchantRequestId: callback.MerchantRequestID ?? null,
    checkoutRequestId: callback.CheckoutRequestID,
    resultCode: callback.ResultCode ?? 1,
    resultDesc: callback.ResultDesc ?? "Unknown callback result",
    amount: readMetadataAmount(items),
    phoneNumber: readMetadataValue(items, "PhoneNumber"),
    mpesaReceiptNumber: readMetadataValue(items, "MpesaReceiptNumber"),
  };
}

function resolveTransaction(parsed: ParsedMpesaStkCallback): PaymentTransaction | null {
  const byCheckout = getTransactionByCheckoutRequestId(parsed.checkoutRequestId);
  if (byCheckout) {
    return byCheckout;
  }

  if (parsed.merchantRequestId) {
    return getTransactionByMerchantRequestId(parsed.merchantRequestId);
  }

  return null;
}

function isTerminalDuplicate(transaction: PaymentTransaction, parsed: ParsedMpesaStkCallback): boolean {
  if (parsed.resultCode === 0 && transaction.status === PAYMENT_TRANSACTION_STATUS.PAID) {
    return true;
  }

  if (parsed.resultCode !== 0 && transaction.status === PAYMENT_TRANSACTION_STATUS.FAILED) {
    return true;
  }

  return false;
}

function persistTransactionDetails(
  transaction: PaymentTransaction,
  parsed: ParsedMpesaStkCallback,
  patch: {
    status: PaymentTransaction["status"];
    paymentReference?: string | null;
    failureReason?: string | null;
  },
): PaymentTransaction {
  const updated: PaymentTransaction = {
    ...transaction,
    status: patch.status,
    paymentReference:
      patch.paymentReference !== undefined ? patch.paymentReference : transaction.paymentReference,
    failureReason:
      patch.failureReason !== undefined ? patch.failureReason : transaction.failureReason,
    merchantRequestId: parsed.merchantRequestId ?? transaction.merchantRequestId,
    checkoutRequestId: parsed.checkoutRequestId,
    phone: parsed.phoneNumber ?? transaction.phone,
    amount: parsed.amount ?? transaction.amount,
    updatedAt: new Date().toISOString(),
  };

  saveTransaction(updated);
  return updated;
}

export async function processMpesaStkCallback(
  payload: MpesaStkCallbackPayload,
): Promise<MpesaCallbackResult & { duplicate?: boolean }> {
  logServerPaymentEvent("mpesa:callback:received", {
    hasBody: Boolean(payload.Body),
  });

  const parsed = parseStkCallbackPayload(payload);
  if (!parsed) {
    logServerPaymentEvent("mpesa:callback:invalid", { reason: "missing CheckoutRequestID" });
    return { handled: false, message: "Invalid callback payload structure." };
  }

  logServerPaymentEvent("mpesa:callback:parsed", {
    checkoutRequestId: parsed.checkoutRequestId,
    merchantRequestId: parsed.merchantRequestId,
    resultCode: parsed.resultCode,
    resultDesc: parsed.resultDesc,
    amount: parsed.amount,
    phoneNumber: parsed.phoneNumber,
    mpesaReceiptNumber: parsed.mpesaReceiptNumber,
  });

  if (isDuplicateCheckoutRequest(parsed.checkoutRequestId)) {
    logServerPaymentEvent("mpesa:callback:duplicate", {
      checkoutRequestId: parsed.checkoutRequestId,
    });
    return {
      handled: true,
      duplicate: true,
      message: "Duplicate callback ignored for CheckoutRequestID.",
    };
  }

  const transaction = resolveTransaction(parsed);
  if (!transaction) {
    logServerPaymentEvent("mpesa:callback:not_found", {
      checkoutRequestId: parsed.checkoutRequestId,
      merchantRequestId: parsed.merchantRequestId,
    });
    return { handled: false, message: "Transaction not found for callback." };
  }

  if (isTerminalDuplicate(transaction, parsed)) {
    markCheckoutRequestProcessed(parsed.checkoutRequestId);
    logServerPaymentEvent("mpesa:callback:already_terminal", {
      transactionId: transaction.transactionId,
      status: transaction.status,
    });
    return {
      handled: true,
      duplicate: true,
      transactionId: transaction.transactionId,
      orderId: transaction.orderId,
      status: transaction.status,
      paymentReference: transaction.paymentReference,
      message: "Callback already processed for this transaction.",
    };
  }

  if (parsed.resultCode === 0) {
    const receipt = parsed.mpesaReceiptNumber ?? generateTestMpesaReceipt();
    const updated = persistTransactionDetails(transaction, parsed, {
      status: PAYMENT_TRANSACTION_STATUS.PAID,
      paymentReference: receipt,
      failureReason: null,
    });

    await publishPaymentConfirmation({
      orderId: updated.orderId,
      outcome: "paid",
      paymentReference: receipt,
      paymentTransactionId: updated.transactionId,
      transaction: {
        transactionId: updated.transactionId,
        amount: updated.amount,
        phone: parsed.phoneNumber ?? updated.phone,
        checkoutRequestId: parsed.checkoutRequestId,
        merchantRequestId: parsed.merchantRequestId,
      },
    });

    markCheckoutRequestProcessed(parsed.checkoutRequestId);

    logServerPaymentEvent("mpesa:callback:paid", {
      transactionId: updated.transactionId,
      orderId: updated.orderId,
      paymentReference: receipt,
    });

    return {
      handled: true,
      transactionId: updated.transactionId,
      orderId: updated.orderId,
      status: PAYMENT_TRANSACTION_STATUS.PAID,
      paymentReference: receipt,
      message: "Payment confirmed via M-Pesa callback.",
    };
  }

  const updated = persistTransactionDetails(transaction, parsed, {
    status: PAYMENT_TRANSACTION_STATUS.FAILED,
    failureReason: parsed.resultDesc,
  });

  await publishPaymentConfirmation({
    orderId: updated.orderId,
    outcome: "failed",
    paymentReference: null,
    paymentTransactionId: updated.transactionId,
    failureReason: parsed.resultDesc,
    transaction: {
      transactionId: updated.transactionId,
      amount: updated.amount,
      phone: parsed.phoneNumber ?? updated.phone,
      checkoutRequestId: parsed.checkoutRequestId,
      merchantRequestId: parsed.merchantRequestId,
    },
  });

  markCheckoutRequestProcessed(parsed.checkoutRequestId);

  logServerPaymentEvent("mpesa:callback:failed", {
    transactionId: updated.transactionId,
    orderId: updated.orderId,
    reason: parsed.resultDesc,
  });

  return {
    handled: true,
    transactionId: updated.transactionId,
    orderId: updated.orderId,
    status: PAYMENT_TRANSACTION_STATUS.FAILED,
    message: parsed.resultDesc,
  };
}
