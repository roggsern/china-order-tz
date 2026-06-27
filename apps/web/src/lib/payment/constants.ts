import type { PaymentCategoryOption, PaymentMethodOption } from "@/lib/types/payment";

export const PAYMENT_CATEGORY_OPTIONS: PaymentCategoryOption[] = [
  {
    category: "mobile_money",
    label: "Mobile Money",
    description: "Pay instantly with your mobile wallet",
    icon: "📱",
  },
  {
    category: "bank_transfer",
    label: "Bank Transfer",
    description: "Transfer from any Tanzanian bank",
    icon: "🏦",
  },
  {
    category: "card",
    label: "Visa / Mastercard",
    description: "Debit or credit card payment",
    icon: "💳",
  },
];

export const MOBILE_MONEY_OPTIONS: PaymentMethodOption[] = [
  {
    code: "mpesa",
    label: "M-Pesa",
    description: "Vodacom M-Pesa",
    icon: "🟢",
    category: "mobile_money",
  },
  {
    code: "airtel_money",
    label: "Airtel Money",
    description: "Airtel Tanzania",
    icon: "🔴",
    category: "mobile_money",
  },
  {
    code: "mixx_by_yas",
    label: "Mixx by Yas",
    description: "Yas Mixx wallet",
    icon: "🟡",
    category: "mobile_money",
  },
  {
    code: "tigo_pesa",
    label: "Tigo Pesa",
    description: "Tigo Pesa wallet",
    icon: "🔵",
    category: "mobile_money",
  },
];

export const BANK_TRANSFER_OPTION: PaymentMethodOption = {
  code: "bank_transfer",
  label: "Bank Transfer",
  description: "NMB, CRDB, NBC & more",
  icon: "🏦",
  category: "bank_transfer",
};

export const CARD_OPTION: PaymentMethodOption = {
  code: "card",
  label: "Visa / Mastercard",
  description: "Secure card payment",
  icon: "💳",
  category: "card",
};

export const SIMPLIFIED_PAYMENT_OPTIONS = [
  {
    code: "mpesa" as const,
    label: "M-Pesa",
    description: "Pay instantly with Vodacom M-Pesa",
    icon: "🟢",
  },
  {
    code: "cod" as const,
    label: "Cash on Delivery",
    description: "Pay when your order arrives",
    icon: "💵",
  },
  {
    code: "bank_transfer" as const,
    label: "Bank Transfer",
    description: "Transfer to our account (details after order)",
    icon: "🏦",
  },
];

export const PAYMENT_METHOD_LABELS: Record<string, string> = {
  mpesa: "M-Pesa",
  cod: "Cash on Delivery",
  airtel_money: "Airtel Money",
  mixx_by_yas: "Mixx by Yas",
  tigo_pesa: "Tigo Pesa",
  bank_transfer: "Bank Transfer",
  card: "Visa / Mastercard",
};

export const PAYMENT_STATUS_LABELS: Record<string, string> = {
  pending: "Pending",
  pending_payment: "Pending Payment",
  paid: "Paid",
  failed: "Failed",
  cancelled: "Cancelled",
  refunded: "Refunded",
};

export const ORDER_STATUS_LABELS: Record<string, string> = {
  pending: "Pending",
  pending_payment: "Pending Payment",
  confirmed: "Confirmed",
  processing: "Processing",
  packed: "Packed",
  shipped: "Shipped",
  in_transit: "In Transit",
  delivered: "Delivered",
  cancelled: "Cancelled",
};
