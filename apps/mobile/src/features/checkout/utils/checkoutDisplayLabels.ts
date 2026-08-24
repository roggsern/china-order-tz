const SESSION_STATUS_LABELS: Record<string, string> = {
  draft: 'In progress',
  validated: 'Ready',
  shipping_selected: 'Shipping selected',
  expired: 'Expired',
  completed: 'Completed',
};

const SHIPPING_CHOICE_LABELS: Record<string, string> = {
  company_shipping: 'Company shipping',
  customer_agent: 'Customer agent',
  self_pickup: 'Self pickup',
  negotiated_delivery: 'Delivery arrangement',
};

export function checkoutSessionStatusLabel(status: string | null | undefined): string {
  const key = status?.trim().toLowerCase() ?? '';
  return SESSION_STATUS_LABELS[key] ?? 'In progress';
}

export function checkoutShippingChoiceLabel(value: string | null | undefined): string | null {
  const key = value?.trim().toLowerCase() ?? '';
  if (!key) return null;
  return SHIPPING_CHOICE_LABELS[key] ?? 'Shipping selected';
}

export function checkoutShippingMethodLabel(value: string | null | undefined): string | null {
  const key = value?.trim().toLowerCase() ?? '';
  if (key === 'air') return 'Air';
  if (key === 'sea') return 'Sea';
  return null;
}
