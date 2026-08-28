import type { PaymentTransactionPayload } from "@/lib/api/customer-payment-orchestrator";
import { isValidPhoneNumber } from "@/lib/phone";
import { PAYMENT_METHOD_CODES } from "@/lib/types/payment";

export const SNIPPE_PROVIDER = "snippe";

export const SNIPPE_MOBILE_MONEY_LABEL = "Mobile Money";

export const SNIPPE_POWERED_BY = "Powered by Snippe";

export const SNIPPE_PHONE_LABEL = "Mobile Money Number";

export const SNIPPE_PHONE_HELPER =
  "A Mobile Money payment request will be sent to this number. Approve it on your phone when prompted.";

export const SNIPPE_CUSTOMER_IDENTITY_MESSAGE =
  "Please complete your name and email details before making payment.";

export const SNIPPE_RECIPIENT_NAME_MESSAGE =
  "The delivery recipient name must include both a first and last name. Update the recipient name and try again.";

export const SNIPPE_WAITING_TITLE = "Payment request sent";

export const SNIPPE_WAITING_BODY =
  "Check your phone and approve the Mobile Money payment request.";

export const SNIPPE_TRANSIENT_STATUS_MESSAGE =
  "We're still checking your payment status. Please wait.";

export const SNIPPE_EXPIRED_MESSAGE =
  "Payment request expired. Please try again.";

export const SNIPPE_CANCELLED_MESSAGE =
  "Payment was cancelled. You can try again when you're ready.";

export const SNIPPE_FAILED_MESSAGE =
  "Payment did not complete. You can try again from this order.";

export function isSnippeTransaction(
  transaction: Pick<PaymentTransactionPayload, "provider"> | null | undefined,
): boolean {
  return transaction?.provider === SNIPPE_PROVIDER;
}

export function isSnippePaymentMethod(method: string | null | undefined): boolean {
  return method === PAYMENT_METHOD_CODES.SNIPPE;
}

export function validateSnippePhoneInput(phone: string): string | null {
  const trimmed = phone.trim();
  if (!trimmed) {
    return "Enter your Mobile Money number.";
  }

  try {
    if (!isValidPhoneNumber(trimmed)) {
      return "Enter a valid Tanzania mobile number, for example 0712345678 or +255712345678.";
    }
  } catch {
    return "Enter a valid Tanzania mobile number, for example 0712345678 or +255712345678.";
  }

  return null;
}

export function isCustomerIdentityStartFailure(
  transaction: PaymentTransactionPayload,
): boolean {
  const responsePayload = transaction.response_payload;
  if (!responsePayload || typeof responsePayload !== "object") {
    return false;
  }

  return responsePayload.error === "customer_identity";
}

export function resolveSnippeStartFailureMessage(
  transaction: PaymentTransactionPayload,
): string {
  if (isCustomerIdentityStartFailure(transaction)) {
    return SNIPPE_CUSTOMER_IDENTITY_MESSAGE;
  }

  if (transaction.status === "failed") {
    const responsePayload = transaction.response_payload;
    if (responsePayload && typeof responsePayload === "object") {
      const messages = responsePayload.messages;
      if (messages && typeof messages === "object") {
        const first = Object.values(messages as Record<string, unknown>)
          .flatMap((value) => (Array.isArray(value) ? value : []))
          .find((value) => typeof value === "string" && value.trim());

        if (typeof first === "string" && first.trim()) {
          return first.trim();
        }
      }
    }
  }

  return SNIPPE_FAILED_MESSAGE;
}

export function resolveSnippeTerminalFailureMessage(
  transaction: PaymentTransactionPayload,
): string {
  if (transaction.status === "cancelled") {
    return SNIPPE_CANCELLED_MESSAGE;
  }

  const verificationPayload = transaction.verification_payload;
  if (verificationPayload && typeof verificationPayload === "object") {
    const failureReason = verificationPayload.failure_reason;
    if (failureReason === "expired") {
      return SNIPPE_EXPIRED_MESSAGE;
    }
  }

  return SNIPPE_FAILED_MESSAGE;
}
