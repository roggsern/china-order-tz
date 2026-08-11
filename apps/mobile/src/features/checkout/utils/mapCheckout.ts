import type {
  ApplyShippingChoiceInput,
  ApplyShippingChoicePayload,
  CheckoutDeliveryAddress,
  CheckoutItem,
  CheckoutPrepare,
  CheckoutSession,
  CheckoutShippingSummary,
  DeliveryAddressInput,
  DeliveryAddressPayload,
  ShippingChoiceOption,
} from '../models/types';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function numberField(data: Record<string, unknown>, key: string): number | null {
  const value = data[key];
  return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function moneyField(data: Record<string, unknown>, key: string): string | number | null {
  const value = data[key];
  if (typeof value === 'string' || typeof value === 'number') return value;
  return null;
}

function boolField(data: Record<string, unknown>, key: string): boolean | null {
  const value = data[key];
  return typeof value === 'boolean' ? value : null;
}

export function mapCheckoutItem(raw: unknown): CheckoutItem | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const productId = stringField(data, 'product_id');
  const productName = stringField(data, 'product_name');
  if (!id || !productId || !productName) return null;

  return {
    id,
    productId,
    productName,
    quantity: numberField(data, 'quantity') ?? 0,
    unitPrice: moneyField(data, 'unit_price'),
    lineSubtotal: moneyField(data, 'subtotal'),
    source: stringField(data, 'source'),
    shippingMethod: stringField(data, 'shipping_method'),
    shippingPrice: moneyField(data, 'shipping_price'),
    shippingSubtotal: moneyField(data, 'shipping_subtotal'),
    deliveryStatus: stringField(data, 'delivery_status'),
  };
}

export function mapDeliveryAddress(raw: unknown): CheckoutDeliveryAddress {
  const data = asRecord(raw);
  return {
    recipientName: stringField(data, 'recipient_name'),
    phone: stringField(data, 'phone'),
    country: stringField(data, 'country'),
    region: stringField(data, 'region'),
    city: stringField(data, 'city'),
    district: stringField(data, 'district'),
    street: stringField(data, 'street'),
    landmark: stringField(data, 'landmark'),
    postalCode: stringField(data, 'postal_code'),
  };
}

export function mapShippingSummary(raw: unknown): CheckoutShippingSummary {
  const data = asRecord(raw);
  return {
    chinaShippingTotal: moneyField(data, 'china_shipping_total'),
    darDeliveryStatus: stringField(data, 'dar_delivery_status'),
    raw: data,
  };
}

/** Map CheckoutResource — monetary fields preserved from server. */
export function mapCheckoutPrepare(raw: unknown): CheckoutPrepare {
  const data = asRecord(raw);
  const customer = asRecord(data.customer);
  const itemsRaw = Array.isArray(data.items) ? data.items : [];

  return {
    customer: {
      firstName: stringField(customer, 'first_name'),
      lastName: stringField(customer, 'last_name'),
      email: stringField(customer, 'email'),
      phone: stringField(customer, 'phone'),
    },
    deliveryAddress: mapDeliveryAddress(data.delivery_address),
    items: itemsRaw
      .map(mapCheckoutItem)
      .filter((item): item is CheckoutItem => item !== null),
    subtotal: moneyField(data, 'subtotal'),
    shippingSummary: mapShippingSummary(data.shipping_summary),
    grandTotal: moneyField(data, 'grand_total'),
    readyForConfirmation: boolField(data, 'ready_for_confirmation') ?? false,
  };
}

/** Map CheckoutSessionResource — no client fingerprint/total math. */
export function mapCheckoutSession(raw: unknown): CheckoutSession {
  const data = asRecord(raw);
  return {
    id: stringField(data, 'id') ?? '',
    cartId: stringField(data, 'cart_id'),
    currency: stringField(data, 'currency') ?? 'TZS',
    status: (stringField(data, 'status') ?? 'draft') as CheckoutSession['status'],
    subtotal: moneyField(data, 'subtotal'),
    discountTotal: moneyField(data, 'discount_total'),
    taxTotal: moneyField(data, 'tax_total'),
    shippingTotal: moneyField(data, 'shipping_total'),
    grandTotal: moneyField(data, 'grand_total'),
    shippingChoice: stringField(data, 'shipping_choice'),
    shippingMethod: stringField(data, 'shipping_method'),
    agentName: stringField(data, 'agent_name'),
    agentContact: stringField(data, 'agent_contact'),
    shippingReady: boolField(data, 'shipping_ready') ?? false,
    isExpired: boolField(data, 'is_expired') ?? false,
    expiresAt: stringField(data, 'expires_at'),
  };
}

export function buildDeliveryAddressPayload(
  input: DeliveryAddressInput,
): DeliveryAddressPayload {
  return {
    recipient_name: input.recipientName.trim(),
    phone: input.phone.trim(),
    country: input.country.trim(),
    region: input.region.trim(),
    city: input.city.trim(),
    district: input.district.trim(),
    street: input.street.trim(),
    landmark: input.landmark?.trim() || null,
    postal_code: input.postalCode?.trim() || null,
  };
}

export function buildShippingChoicePayload(
  input: ApplyShippingChoiceInput,
): ApplyShippingChoicePayload {
  const payload: ApplyShippingChoicePayload = {
    shipping_choice: input.shippingChoice,
  };

  if (input.shippingChoice === 'company_shipping') {
    payload.shipping_method = input.shippingMethod ?? null;
  } else {
    payload.shipping_method = null;
  }

  if (input.shippingChoice === 'customer_agent') {
    payload.agent_name = input.agentName?.trim() || null;
    payload.agent_contact = input.agentContact?.trim() || null;
  } else {
    payload.agent_name = null;
    payload.agent_contact = null;
  }

  return payload;
}

/**
 * Allowed shipping choices from Contract + server item source markers.
 * Mixed carts are rejected by the API before this runs.
 */
export function shippingChoicesForItems(items: CheckoutItem[]): ShippingChoiceOption[] {
  const hasChina = items.some((item) => item.source === 'China');
  const hasDar = items.some((item) => item.source === 'Dar');

  if (hasChina && !hasDar) {
    return [
      { value: 'company_shipping', label: 'Company Shipping' },
      { value: 'customer_agent', label: 'Customer Agent' },
    ];
  }

  if (hasDar && !hasChina) {
    return [
      { value: 'self_pickup', label: 'Self Pickup' },
      { value: 'negotiated_delivery', label: 'Delivery Arrangement' },
    ];
  }

  return [];
}

export function isReadyForPayment(session: CheckoutSession | null | undefined): boolean {
  if (!session?.id) return false;
  if (session.isExpired || session.status === 'expired' || session.status === 'completed') {
    return false;
  }
  return Boolean(session.shippingReady && session.shippingChoice);
}

export function isStaleOrExpiredCheckoutError(error: unknown): boolean {
  if (!error || typeof error !== 'object') return false;
  const message =
    'message' in error && typeof (error as { message?: unknown }).message === 'string'
      ? (error as { message: string }).message
      : '';
  const errors =
    'errors' in error && (error as { errors?: unknown }).errors
      ? (error as { errors: Record<string, string[]> }).errors
      : {};
  const fieldText = Object.values(errors)
    .flat()
    .join(' ');
  const haystack = `${message} ${fieldText}`.toLowerCase();
  return /stale|expired|refresh checkout/i.test(haystack);
}

export function formatCheckoutMoney(
  value: string | number | null | undefined,
  currency = 'TZS',
): string {
  return formatCustomerMoney(value, currency);
}

export function journeyLabelFromCheckoutItems(items: CheckoutItem[]): string {
  const hasChina = items.some((item) => item.source === 'China');
  const hasDar = items.some((item) => item.source === 'Dar');
  if (hasChina && !hasDar) return 'Order from China';
  if (hasDar && !hasChina) return 'Buy from TZ';
  return 'Marketplace';
}
