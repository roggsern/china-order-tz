export type CheckoutMoney = string | number | null;

export type CheckoutCustomer = {
  firstName: string | null;
  lastName: string | null;
  email: string | null;
  phone: string | null;
};

export type CheckoutDeliveryAddress = {
  recipientName: string | null;
  phone: string | null;
  country: string | null;
  region: string | null;
  city: string | null;
  district: string | null;
  street: string | null;
  landmark: string | null;
  postalCode: string | null;
};

export type CheckoutItem = {
  id: string;
  productId: string;
  productName: string;
  quantity: number;
  unitPrice: CheckoutMoney;
  lineSubtotal: CheckoutMoney;
  /** Server source label: China | Dar */
  source: string | null;
  shippingMethod: string | null;
  shippingPrice: CheckoutMoney;
  shippingSubtotal: CheckoutMoney;
  deliveryStatus: string | null;
};

export type CheckoutShippingSummary = {
  chinaShippingTotal: CheckoutMoney;
  darDeliveryStatus: string | null;
  raw: Record<string, unknown>;
};

/** Prepare / GET checkout preview (CheckoutResource). */
export type CheckoutPrepare = {
  customer: CheckoutCustomer;
  deliveryAddress: CheckoutDeliveryAddress;
  items: CheckoutItem[];
  subtotal: CheckoutMoney;
  shippingSummary: CheckoutShippingSummary;
  grandTotal: CheckoutMoney;
  readyForConfirmation: boolean;
  /** Backend option list when present; otherwise inferred from item source. */
  shippingChoices: ShippingChoiceOption[];
};

export type CheckoutSessionStatus =
  | 'draft'
  | 'validated'
  | 'expired'
  | 'completed'
  | (string & {});

export type CheckoutShippingChoiceValue =
  | 'company_shipping'
  | 'customer_agent'
  | 'self_pickup'
  | 'negotiated_delivery';

export type CheckoutSession = {
  id: string;
  cartId: string | null;
  currency: string;
  status: CheckoutSessionStatus;
  subtotal: CheckoutMoney;
  discountTotal: CheckoutMoney;
  taxTotal: CheckoutMoney;
  shippingTotal: CheckoutMoney;
  grandTotal: CheckoutMoney;
  shippingChoice: CheckoutShippingChoiceValue | string | null;
  shippingMethod: 'air' | 'sea' | string | null;
  agentName: string | null;
  agentContact: string | null;
  shippingReady: boolean;
  isExpired: boolean;
  expiresAt: string | null;
};

export type ShippingChoiceOption = {
  value: CheckoutShippingChoiceValue;
  label: string;
  /** Absent or true = selectable. False is hidden, never shown as available. */
  available?: boolean;
};

export type ApplyShippingChoiceInput = {
  shippingChoice: CheckoutShippingChoiceValue;
  shippingMethod?: 'air' | 'sea' | null;
  agentName?: string | null;
  agentContact?: string | null;
};

export type ApplyShippingChoicePayload = {
  shipping_choice: CheckoutShippingChoiceValue;
  shipping_method?: 'air' | 'sea' | null;
  agent_name?: string | null;
  agent_contact?: string | null;
};

export type DeliveryAddressInput = {
  recipientName: string;
  phone: string;
  country: string;
  region: string;
  city: string;
  district: string;
  street: string;
  landmark?: string | null;
  postalCode?: string | null;
};

export type DeliveryAddressPayload = {
  recipient_name: string;
  phone: string;
  country: string;
  region: string;
  city: string;
  district: string;
  street: string;
  landmark?: string | null;
  postal_code?: string | null;
};
