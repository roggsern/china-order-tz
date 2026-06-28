import type { PaymentProviderCode } from "@/lib/payments/providers/types";

/** Gateway transaction lifecycle states. */
export const PAYMENT_TRANSACTION_STATUS = {
  PENDING: "pending",
  PROCESSING: "processing",
  PAID: "paid",
  FAILED: "failed",
} as const;

export type PaymentTransactionStatus =
  (typeof PAYMENT_TRANSACTION_STATUS)[keyof typeof PAYMENT_TRANSACTION_STATUS];

export type PaymentMode = "test" | "live";

export type MpesaConfig = {
  consumerKey: string;
  consumerSecret: string;
  shortcode: string;
  passkey: string;
  callbackUrl: string;
  baseUrl: string;
  stkPushPath: string;
  oauthPath: string;
};

export type PaymentTransaction = {
  transactionId: string;
  orderId: string;
  orderNumber: string;
  amount: number;
  phone: string;
  status: PaymentTransactionStatus;
  paymentReference: string | null;
  checkoutRequestId: string | null;
  merchantRequestId: string | null;
  provider: PaymentProviderCode;
  mode: PaymentMode;
  failureReason: string | null;
  createdAt: string;
  updatedAt: string;
};

export type InitiateStkPushInput = {
  orderId: string;
  orderNumber: string;
  amount: number;
  phone: string;
  accountReference?: string;
  description?: string;
  provider?: PaymentProviderCode;
  paymentMethod?: string;
};

export type InitiateStkPushResult = {
  success: boolean;
  transactionId: string | null;
  status: PaymentTransactionStatus;
  checkoutRequestId: string | null;
  message: string;
  mode: PaymentMode;
  provider?: PaymentProviderCode;
};

export type VerifyPaymentResult = {
  transactionId: string;
  orderId: string;
  orderNumber: string;
  status: PaymentTransactionStatus;
  paymentReference: string | null;
  amount: number;
  message: string;
  mode: PaymentMode;
  provider?: PaymentProviderCode;
};

export type SimulateStkPushResult = {
  success: boolean;
  transactionId: string;
  paymentReference: string;
  status: typeof PAYMENT_TRANSACTION_STATUS.PAID;
  orderId: string;
  orderNumber: string;
  amount: number;
  message: string;
  mode: "test";
};

export type MpesaStkCallbackPayload = {
  Body?: {
    stkCallback?: {
      MerchantRequestID?: string;
      CheckoutRequestID?: string;
      ResultCode?: number;
      ResultDesc?: string;
      CallbackMetadata?: {
        Item?: Array<{ Name?: string; Value?: string | number }>;
      };
    };
  };
};

export type MpesaCallbackResult = {
  handled: boolean;
  duplicate?: boolean;
  transactionId?: string;
  orderId?: string;
  status?: PaymentTransactionStatus;
  paymentReference?: string | null;
  message: string;
  provider?: PaymentProviderCode;
};

export type ParsedMpesaStkCallback = {
  merchantRequestId: string | null;
  checkoutRequestId: string;
  resultCode: number;
  resultDesc: string;
  amount: number | null;
  phoneNumber: string | null;
  mpesaReceiptNumber: string | null;
};

export type PaymentConfigResponse = {
  testMode: boolean;
  mode: PaymentMode;
  simulateEnabled: boolean;
  providers: PaymentProviderCode[];
};

export type DarajaStkPushResponse = {
  MerchantRequestID: string;
  CheckoutRequestID: string;
  ResponseCode: string;
  ResponseDescription: string;
  CustomerMessage: string;
};

export type DarajaOAuthResponse = {
  access_token: string;
  expires_in: string;
};

/** @deprecated Use InitiateStkPushInput */
export type InitiatePaymentInput = InitiateStkPushInput;

/** @deprecated Use InitiateStkPushResult */
export type InitiatePaymentResult = InitiateStkPushResult;
