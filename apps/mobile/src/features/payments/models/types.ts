export type PaymentMethodCode = 'nmb' | 'snippe' | 'cash' | (string & {});

/** Backend active payment attempt — Wave 1 recovery reads this, never local storage. */
export type ActivePaymentTransactionRef = {
  id: string;
  status: string;
  provider: string | null;
};

export type PaymentMethodAvailability = {
  code: string;
  enabled: boolean;
  available: boolean;
  selectable: boolean;
};

export type PaymentMethodsAvailability = {
  defaultProvider: string | null;
  enabledMethods: string[];
  methods: PaymentMethodAvailability[];
};

export type PaymentTransactionStatus =
  | 'pending'
  | 'processing'
  | 'successful'
  | 'failed'
  | 'cancelled'
  | 'expired'
  | (string & {});

export type PaymentOrderSummary = {
  id: string;
  orderNumber: string | null;
  status: string | null;
  grandTotal: string | number | null;
  currency: string | null;
};

export type PaymentTransaction = {
  id: string;
  orderId: string;
  provider: string | null;
  providerReference: string | null;
  merchantReference: string | null;
  currency: string;
  amount: string | number | null;
  status: PaymentTransactionStatus;
  checkoutUrl: string | null;
  successIndicator: string | null;
  order: PaymentOrderSummary | null;
  initiatedAt: string | null;
  completedAt: string | null;
};

/** Minimal order identity from POST /orders/from-checkout/{session}. */
export type PaymentOrder = {
  id: string;
  orderNumber: string | null;
  status: string | null;
  currency: string;
  grandTotal: string | number | null;
  checkoutSessionId: string | null;
};

export type StartPaymentPayload = {
  provider?: string;
};

export type ReconcileNmbReturnInput = {
  paymentTransactionId: string;
  merchantReference: string;
  successIndicator: string;
  resultIndicator: string;
  orderId?: string | null;
};

export type ReconcileNmbReturnPayload = {
  payment_transaction_id: string;
  merchant_reference: string;
  success_indicator: string;
  result_indicator: string;
  order_id?: string;
};

export type NmbBrowserReturnParams = {
  resultIndicator: string | null;
  orderId: string | null;
  merchantReference: string | null;
  paymentTransactionId: string | null;
};
