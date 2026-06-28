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

/** UI phases for the M-Pesa STK Push payment screen. */
export type StkFlowPhase =
  | "initiating"
  | "sending"
  | "waiting_pin"
  | "confirming"
  | "success"
  | "failed";

/** @deprecated Use StkFlowPhase */
export type StkVisualStep = StkFlowPhase | "pending" | "processing" | "complete";

export const STK_UI_STEP_MS = {
  /** Step 1 — Initiating request */
  initiating: 1_000,
  /** Step 2 — Sending STK push (runs after step 1) */
  sending: 2_000,
  /** How long "Confirming" shows before success screen */
  confirmingHold: 700,
} as const;

export const STK_FLOW_STEPS = [
  { id: "initiating" as const, label: "Initiating request" },
  { id: "sending" as const, label: "Sending STK push" },
  { id: "waiting_pin" as const, label: "Waiting for PIN" },
  { id: "confirming" as const, label: "Confirming payment" },
] as const;

export type StkFlowStepId = (typeof STK_FLOW_STEPS)[number]["id"];

const STEP_ORDER: StkFlowStepId[] = ["initiating", "sending", "waiting_pin", "confirming"];

export function resolveStkFlowPhase(input: {
  elapsedMs: number;
  paymentFailed: boolean;
  paymentPaid: boolean;
  msSincePaid: number | null;
}): StkFlowPhase {
  if (input.paymentFailed) {
    return "failed";
  }

  if (input.paymentPaid && input.msSincePaid !== null) {
    if (input.msSincePaid >= STK_UI_STEP_MS.confirmingHold) {
      return "success";
    }
    return "confirming";
  }

  const afterSending = STK_UI_STEP_MS.initiating + STK_UI_STEP_MS.sending;

  if (input.elapsedMs >= afterSending) {
    return "waiting_pin";
  }

  if (input.elapsedMs >= STK_UI_STEP_MS.initiating) {
    return "sending";
  }

  return "initiating";
}

/** @deprecated Use resolveStkFlowPhase */
export function resolveStkVisualStep(input: {
  paymentFailed: boolean;
  paymentPaid: boolean;
  confirmingStarted: boolean;
  elapsedMs: number;
}): StkVisualStep {
  const phase = resolveStkFlowPhase({
    elapsedMs: input.elapsedMs,
    paymentFailed: input.paymentFailed,
    paymentPaid: input.paymentPaid,
    msSincePaid: input.paymentPaid && input.confirmingStarted ? STK_UI_STEP_MS.confirmingHold : null,
  });

  if (phase === "initiating") return "pending";
  if (phase === "sending") return "processing";
  return phase;
}

export function getStkPhaseHeadline(phase: StkFlowPhase): string {
  switch (phase) {
    case "initiating":
      return "Initiating M-Pesa STK Push…";
    case "sending":
      return "Sending STK Push…";
    case "waiting_pin":
      return "Check your phone to complete payment";
    case "confirming":
      return "Confirming payment…";
    case "success":
      return "Payment Successful";
    case "failed":
      return "Payment failed";
    default:
      return "Processing payment…";
  }
}

export function getStkPhaseSubtext(phase: StkFlowPhase, testMode: boolean): string {
  switch (phase) {
    case "initiating":
      return "Connecting securely to M-Pesa…";
    case "sending":
      return "A payment prompt is being sent to your registered number.";
    case "waiting_pin":
      return testMode
        ? "Enter your PIN on the simulated STK prompt. Test mode auto-confirms shortly."
        : "Enter your M-Pesa PIN on your phone when the STK prompt appears.";
    case "confirming":
      return "Verifying your transaction with Safaricom…";
    case "success":
      return "Your payment has been received. Redirecting to your order confirmation…";
    case "failed":
      return "The payment was not completed. You can retry from the payment page.";
    default:
      return "";
  }
}

export function getStkStepIndex(phase: StkFlowPhase): number {
  if (phase === "failed") return -1;
  if (phase === "success") return STEP_ORDER.length;
  if (phase === "confirming") return 3;
  return STEP_ORDER.indexOf(phase as StkFlowStepId);
}

export function orderSnapshotForProcessing(order: Order | null) {
  return {
    paymentPaid: order?.paymentStatus === "paid",
    paymentFailed: order?.paymentStatus === "failed",
    orderProcessing: order?.status === "processing",
  };
}
