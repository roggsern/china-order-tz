import type {
  PaymentMode,
  PaymentTransactionStatus,
  VerifyPaymentResult,
} from "@/lib/payments/types";

/** Supported payment gateway providers. */
export const PAYMENT_PROVIDER = {
  MPESA: "mpesa",
  NMB: "nmb",
  SELCOM: "selcom",
} as const;

export type PaymentProviderCode = (typeof PAYMENT_PROVIDER)[keyof typeof PAYMENT_PROVIDER];

export type PaymentInitiateContext = {
  orderId: string;
  orderNumber: string;
  amount: number;
  phone: string;
  accountReference?: string;
  description?: string;
};

/** Normalized initiate response consumed by checkout and API routes. */
export type NormalizedPaymentResponse = {
  success: boolean;
  transactionId: string | null;
  message: string;
  status: PaymentTransactionStatus;
  checkoutRequestId: string | null;
  mode: PaymentMode;
  provider: PaymentProviderCode;
};

export type PaymentCallbackResult = {
  handled: boolean;
  duplicate?: boolean;
  transactionId?: string;
  orderId?: string;
  status?: PaymentTransactionStatus;
  paymentReference?: string | null;
  message: string;
  provider?: PaymentProviderCode;
};

export interface PaymentProvider {
  readonly code: PaymentProviderCode;
  initiatePayment(context: PaymentInitiateContext): Promise<NormalizedPaymentResponse>;
  handleCallback(payload: unknown): Promise<PaymentCallbackResult>;
  verifyPayment(transactionId: string): VerifyPaymentResult | null;
  canHandleCallback(payload: unknown): boolean;
}

/** Mock provider callback shape for NMB / Selcom test integrations. */
export type MockProviderCallbackPayload = {
  provider: PaymentProviderCode;
  transactionId: string;
  status: "paid" | "failed";
  reference?: string;
  message?: string;
};
