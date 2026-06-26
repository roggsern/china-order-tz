export const PAYMENT_STATUS = {
  PENDING: "pending",
  PENDING_PAYMENT: "pending_payment",
  PAID: "paid",
  FAILED: "failed",
  CANCELLED: "cancelled",
  REFUNDED: "refunded",
} as const;

export type PaymentStatus = (typeof PAYMENT_STATUS)[keyof typeof PAYMENT_STATUS];

/** Primary checkout payment states stored on orders. */
export type CorePaymentStatus = "pending" | "paid" | "failed";

export const PAYMENT_METHOD_CODES = {
  MPESA: "mpesa",
  COD: "cod",
  AIRTEL_MONEY: "airtel_money",
  MIXX_BY_YAS: "mixx_by_yas",
  TIGO_PESA: "tigo_pesa",
  BANK_TRANSFER: "bank_transfer",
  CARD: "card",
} as const;

export type PaymentMethodCode = (typeof PAYMENT_METHOD_CODES)[keyof typeof PAYMENT_METHOD_CODES];

export type PaymentCategory = "mobile_money" | "bank_transfer" | "card";

export type PaymentMethodSelection = {
  category: PaymentCategory;
  code: PaymentMethodCode;
};

export type PaymentMethodOption = {
  code: PaymentMethodCode;
  label: string;
  description: string;
  icon: string;
  category: PaymentCategory;
};

export type PaymentCategoryOption = {
  category: PaymentCategory;
  label: string;
  description: string;
  icon: string;
};
