import type { CorePaymentStatus } from "@/lib/types/payment";

export type PaymentTransactionStatus = CorePaymentStatus;

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
  provider: "mpesa";
  mode: "test" | "live";
  failureReason: string | null;
  createdAt: string;
  updatedAt: string;
};

export type InitiatePaymentInput = {
  orderId: string;
  orderNumber: string;
  amount: number;
  phone: string;
  accountReference?: string;
  description?: string;
};

export type InitiatePaymentResult = {
  success: boolean;
  transactionId: string | null;
  status: PaymentTransactionStatus;
  checkoutRequestId: string | null;
  message: string;
  mode: "test" | "live";
};

export type VerifyPaymentResult = {
  transactionId: string;
  orderId: string;
  orderNumber: string;
  status: PaymentTransactionStatus;
  paymentReference: string | null;
  amount: number;
  message: string;
  mode: "test" | "live";
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
