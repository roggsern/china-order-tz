export {
  getPaymentMode,
  isPaymentTestMode,
  getMpesaConfig,
  assertMpesaLiveConfig,
} from "@/lib/payments/config";

export type { PaymentMode } from "@/lib/payments/config";

/** @deprecated Import from @/lib/payments/config */
export { canUseLiveMpesa } from "@/lib/payments/config";
