import { PaymentOrchestratorApiError } from "@/lib/api/customer-payment-orchestrator";

export type ActivePaymentTransactionRef = {
  id: string;
  status: string;
  provider: string | null;
};

export type PayNowView =
  | { kind: "selector" }
  | { kind: "paid" }
  | { kind: "recovery"; transaction: ActivePaymentTransactionRef }
  | { kind: "not_payable"; reason: "paid" | "cancelled" | "other" };

export function isActivePaymentTransactionStatus(status: string): boolean {
  return status === "pending" || status === "processing";
}

export function isTerminalFailedPaymentStatus(status: string): boolean {
  return status === "failed" || status === "cancelled";
}

export function isSuccessfulPaymentTransactionStatus(status: string): boolean {
  return status === "successful";
}

export function resolveRefreshedTransactionView(
  status: string,
): "paid" | "selector" | "recovery" {
  if (isSuccessfulPaymentTransactionStatus(status)) {
    return "paid";
  }

  if (isTerminalFailedPaymentStatus(status)) {
    return "selector";
  }

  return "recovery";
}

export function resolvePayNowView(input: {
  canPay: boolean;
  orderStatus: string;
  paymentStatus?: string | null;
  activeTransaction?: ActivePaymentTransactionRef | null;
}): PayNowView {
  if (
    input.paymentStatus === "paid" ||
    input.orderStatus === "paid" ||
    input.orderStatus === "confirmed" ||
    input.orderStatus === "processing" ||
    input.orderStatus === "shipped" ||
    input.orderStatus === "delivered" ||
    input.orderStatus === "completed"
  ) {
    if (!input.canPay) {
      return { kind: "paid" };
    }
  }

  if (!input.canPay) {
    if (
      input.orderStatus === "cancelled" ||
      input.orderStatus === "refunded" ||
      input.orderStatus === "refund_pending" ||
      input.paymentStatus === "refunded"
    ) {
      return { kind: "not_payable", reason: "cancelled" };
    }

    return { kind: "not_payable", reason: "other" };
  }

  const active = input.activeTransaction;
  if (active && isActivePaymentTransactionStatus(active.status)) {
    return { kind: "recovery", transaction: active };
  }

  return { kind: "selector" };
}

export function isPaymentInProgressError(error: unknown): boolean {
  if (!(error instanceof PaymentOrchestratorApiError)) {
    return false;
  }

  if (error.code === "payment_in_progress") {
    return true;
  }

  return /active payment is already in progress/i.test(error.message);
}

export function recoveryFromStartError(
  error: PaymentOrchestratorApiError,
): ActivePaymentTransactionRef | null {
  if (!isPaymentInProgressError(error) || !error.paymentTransactionId) {
    return null;
  }

  return {
    id: error.paymentTransactionId,
    status: error.paymentTransactionStatus ?? "processing",
    provider: error.provider ?? null,
  };
}

export function paymentInProgressCustomerMessage(): string {
  return "There's already a payment request pending for this order.";
}
