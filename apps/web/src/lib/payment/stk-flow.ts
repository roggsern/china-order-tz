import type { Order } from "@/lib/types/order";
import { savePaymentTransaction } from "@/lib/payment/payment-session";

type RouterLike = {
  replace: (href: string) => void;
};

export function redirectToPaymentProcessing(
  router: RouterLike,
  orderId: string,
  transactionId: string,
  options?: { simulated?: boolean },
): void {
  savePaymentTransaction(orderId, transactionId);
  const params = new URLSearchParams({ transactionId });
  if (options?.simulated) {
    params.set("simulated", "1");
  }
  router.replace(`/checkout/payment/processing/${orderId}?${params.toString()}`);
}

export function buildPaymentProcessingHref(
  orderId: string,
  transactionId: string,
  options?: { simulated?: boolean },
): string {
  const params = new URLSearchParams({ transactionId });
  if (options?.simulated) {
    params.set("simulated", "1");
  }
  return `/checkout/payment/processing/${orderId}?${params.toString()}`;
}

export type StkVisualStep = "pending" | "processing" | "confirming" | "complete" | "failed";

export function resolveStkVisualStep(input: {
  paymentFailed: boolean;
  paymentPaid: boolean;
  confirmingStarted: boolean;
  elapsedMs: number;
}): StkVisualStep {
  if (input.paymentFailed) {
    return "failed";
  }
  if (input.paymentPaid && input.confirmingStarted) {
    return "complete";
  }
  if (input.paymentPaid || input.confirmingStarted) {
    return "confirming";
  }
  if (input.elapsedMs >= 1200) {
    return "processing";
  }
  return "pending";
}

export function orderSnapshotForProcessing(order: Order | null) {
  return {
    paymentPaid: order?.paymentStatus === "paid",
    paymentFailed: order?.paymentStatus === "failed",
    orderProcessing: order?.status === "processing",
  };
}
