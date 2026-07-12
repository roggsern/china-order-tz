/** NMB Hosted Checkout — public helpers for checkout UI integration. */
export {
  consumeNmbPendingPaymentId,
  saveNmbCheckoutContext,
  setNmbPendingPaymentId,
} from "@/lib/nmb/checkout-context";
export { initiateNmbPaymentSession } from "@/lib/nmb/payment-session-api";
export type { NmbCheckoutContext, NmbPaymentSessionResponse } from "@/lib/nmb/types";
