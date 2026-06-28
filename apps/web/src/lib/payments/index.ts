/** Client checkout orchestrator — safe to import from UI components. */
export { paymentService, PaymentService } from "@/lib/payments/checkout-service";

export * from "@/lib/payments/types";

export {
  getPaymentMode,
  isPaymentTestMode,
  isPaymentLiveMode,
  getMpesaConfig,
  assertMpesaLiveConfig,
  canUseLiveMpesa,
  TEST_STK_AUTO_COMPLETE_MS,
} from "@/lib/payments/config";

export {
  processMpesaStkCallback,
  parseStkCallbackPayload,
  isValidMpesaCallbackPayload,
} from "@/lib/payments/mpesa-callback";

export {
  saveTransaction,
  getTransaction,
  getTransactionByCheckoutRequestId,
  getTransactionByMerchantRequestId,
  updateTransactionStatus,
} from "@/lib/payments/transaction-store";

export * from "@/lib/payments/providers";

/** Server-only — import from @/lib/payments/paymentService in API routes. */
export {
  PaymentService as ServerPaymentService,
  paymentService as serverPaymentService,
  serverPaymentGateway,
} from "@/lib/payments/paymentService";

export {
  paymentRouter,
  PaymentRouter,
  mapPaymentMethodToProvider,
} from "@/lib/payments/payment-router";
