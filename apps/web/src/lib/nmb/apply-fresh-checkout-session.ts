import type { PaymentTransactionPayload } from "@/lib/api/customer-payment-orchestrator";
import {
  patchNmbCheckoutContext,
  readNmbCheckoutContext,
  saveNmbCheckoutContext,
} from "@/lib/nmb/checkout-context";
import { prepareNmbHostedCheckoutLaunch } from "@/lib/nmb/orchestrator-checkout";

export type FreshNmbCheckoutSession = {
  sessionId: string;
  successIndicator: string | null;
  transaction: PaymentTransactionPayload;
};

/**
 * Apply a freshly minted NMB session onto local checkout context.
 * Replaces any stale gatewaySessionId / successIndicator.
 */
export function applyFreshNmbCheckoutSession(
  transaction: PaymentTransactionPayload,
  paymentTransactionId: string,
): FreshNmbCheckoutSession {
  const sessionId = transaction.provider_reference?.trim() || "";

  if (!sessionId) {
    throw new Error("NMB did not return a checkout session id.");
  }

  const successIndicator = transaction.success_indicator ?? null;

  prepareNmbHostedCheckoutLaunch(transaction);
  saveNmbCheckoutContext({
    ...(readNmbCheckoutContext() ?? {}),
    paymentId: paymentTransactionId,
    paymentTransactionId,
    gatewaySessionId: sessionId,
    successIndicator,
    orderId: transaction.order_id,
  });
  patchNmbCheckoutContext({
    paymentId: paymentTransactionId,
    paymentTransactionId,
    gatewaySessionId: sessionId,
    successIndicator,
    orderId: transaction.order_id,
  });

  return {
    sessionId,
    successIndicator,
    transaction,
  };
}
