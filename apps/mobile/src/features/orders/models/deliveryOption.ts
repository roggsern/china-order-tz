/** GET/POST/PATCH /orders/{id}/delivery-option — post-pay handoff, not checkout. */

export type DeliveryOptionSnapshot = {
  id: string;
  orderId: string | null;
  deliveryType: string;
  deliveryTypeLabel: string | null;
  shippingMethod: string | null;
  shippingMethodLabel: string | null;
  deliveryStatus: string | null;
  deliveryStatusLabel: string | null;
  lastMileReceivingMethod: string | null;
  lastMileReceivingMethodLabel: string | null;
  agentName: string | null;
  agentContact: string | null;
  notes: string | null;
  confirmedAt: string | null;
};

export type DeliveryAvailableOptions = {
  market: string;
  marketLabel: string;
  deliveryTypes: { value: string; label: string }[];
  shippingMethods: { value: string; label: string }[];
};

export type DeliveryOptionShow = {
  deliveryOption: DeliveryOptionSnapshot | null;
  available: DeliveryAvailableOptions | null;
};

export type SelectDeliveryOptionInput = {
  orderId: string;
  deliveryType: string;
  shippingMethod?: string | null;
  agentName?: string | null;
  agentContact?: string | null;
  notes?: string | null;
};

export type UpdateDeliveryOptionInput = {
  orderId: string;
  deliveryType?: string;
  shippingMethod?: string | null;
  agentName?: string | null;
  agentContact?: string | null;
  notes?: string | null;
  deliveryStatus?: string;
};
