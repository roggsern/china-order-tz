import type { PaymentTransactionPayload } from "@/lib/api/customer-payment-orchestrator";

export type ReturnPhase =
  | "pending"
  | "indicator_failed"
  | "needs_auth"
  | "confirming"
  | "redirecting"
  | "failed";

export type IndicatorGate = "ready" | "failed" | "missing";

const WAITING_STATUSES = new Set(["pending", "processing"]);

export function resolveIndicatorGate(
  resultIndicator: string | null,
  successIndicator: string | null | undefined,
): IndicatorGate {
  if (!resultIndicator) {
    return "missing";
  }

  if (successIndicator) {
    return resultIndicator === successIndicator ? "ready" : "failed";
  }

  return "ready";
}

export function looksLikeMerchantReference(value: string | null | undefined): boolean {
  return typeof value === "string" && /^COTZ-PAY-\d{8}-\d{6}$/.test(value.trim());
}

export function looksLikeOrderUuid(value: string | null | undefined): boolean {
  return (
    typeof value === "string" &&
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value.trim())
  );
}

export function shouldAttemptReturnResolution(input: {
  paymentTransactionId: string | null;
  resultIndicator: string | null;
  orderId: string | null;
  merchantReference: string | null;
}): boolean {
  if (input.paymentTransactionId) {
    return false;
  }

  if (input.resultIndicator) {
    return true;
  }

  return looksLikeMerchantReference(input.orderId) || looksLikeOrderUuid(input.orderId) || Boolean(input.merchantReference);
}

export function resolveInitialReturnPhase(input: {
  indicatorGate: IndicatorGate;
  paymentTransactionId: string | null;
  resultIndicator: string | null;
  orderId: string | null;
  merchantReference: string | null;
}): ReturnPhase {
  if (input.indicatorGate === "failed") {
    return "indicator_failed";
  }

  if (
    input.paymentTransactionId ||
    input.resultIndicator ||
    looksLikeMerchantReference(input.orderId) ||
    looksLikeOrderUuid(input.orderId) ||
    input.merchantReference
  ) {
    return "confirming";
  }

  return "pending";
}

export function shouldReconcileReturn(input: {
  indicatorGate: IndicatorGate;
  paymentTransactionId: string | null;
}): boolean {
  return input.indicatorGate !== "failed" && Boolean(input.paymentTransactionId);
}

export function resolvePhaseAfterTransaction(
  transaction: Pick<PaymentTransactionPayload, "status">,
  currentPhase: ReturnPhase,
): ReturnPhase {
  if (transaction.status === "successful") {
    return "redirecting";
  }

  if (transaction.status === "failed" || transaction.status === "cancelled") {
    return "failed";
  }

  if (WAITING_STATUSES.has(transaction.status)) {
    return "confirming";
  }

  return currentPhase === "redirecting" || currentPhase === "failed" ? currentPhase : "confirming";
}

export function resolvePhaseAfterRefreshError(currentPhase: ReturnPhase): ReturnPhase {
  return currentPhase === "redirecting" || currentPhase === "failed" ? currentPhase : "confirming";
}

export function buildSuccessHref(
  transaction: PaymentTransactionPayload,
  orderId: string | null,
  localOrderId: string | null,
): string {
  const resolvedOrderId =
    transaction.order_id ?? transaction.order?.id ?? orderId ?? localOrderId;

  if (resolvedOrderId) {
    return `/order-success/${resolvedOrderId}`;
  }

  return "/orders";
}

export function getReturnPhaseCopy(phase: ReturnPhase): { title: string; body: string } {
  switch (phase) {
    case "confirming":
      return {
        title: "Almost done. We are confirming your payment with NMB.",
        body: "Please wait while we verify your payment with the bank.",
      };
    case "redirecting":
      return {
        title: "Payment confirmed",
        body: "Redirecting you to your order…",
      };
    case "failed":
    case "indicator_failed":
      return {
        title: "Payment failed",
        body: "Your NMB payment could not be confirmed. You can return to checkout and try again.",
      };
    case "needs_auth":
      return {
        title: "Sign in to confirm payment",
        body: "Your NMB payment return was received, but we need you to sign in before we can confirm it with our server.",
      };
    default:
      return {
        title: "Payment status pending",
        body: "We could not confirm the final payment result yet. You can review your order status shortly.",
      };
  }
}
