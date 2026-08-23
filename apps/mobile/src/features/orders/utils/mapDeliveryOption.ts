import type {
  DeliveryAvailableOptions,
  DeliveryOptionSnapshot,
  DeliveryOptionShow,
} from '../models/deliveryOption';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  if (typeof value === 'string' && value.trim() !== '') return value.trim();
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return null;
}

function mapLabeledOptions(raw: unknown): { value: string; label: string }[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((row) => {
      const item = asRecord(row);
      const value = stringField(item, 'value');
      const label = stringField(item, 'label') ?? value;
      if (!value) return null;
      return { value, label: label ?? value };
    })
    .filter((row): row is { value: string; label: string } => row !== null);
}

export function mapDeliveryOptionSnapshot(raw: unknown): DeliveryOptionSnapshot | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const deliveryType = stringField(data, 'delivery_type');
  if (!id || !deliveryType) return null;

  return {
    id,
    orderId: stringField(data, 'order_id'),
    deliveryType,
    deliveryTypeLabel: stringField(data, 'delivery_type_label'),
    shippingMethod: stringField(data, 'shipping_method'),
    shippingMethodLabel: stringField(data, 'shipping_method_label'),
    deliveryStatus: stringField(data, 'delivery_status'),
    deliveryStatusLabel: stringField(data, 'delivery_status_label'),
    lastMileReceivingMethod: stringField(data, 'last_mile_receiving_method'),
    lastMileReceivingMethodLabel: stringField(data, 'last_mile_receiving_method_label'),
    agentName: stringField(data, 'agent_name'),
    agentContact: stringField(data, 'agent_contact'),
    notes: stringField(data, 'notes'),
    confirmedAt: stringField(data, 'confirmed_at'),
  };
}

export function mapDeliveryAvailableOptions(raw: unknown): DeliveryAvailableOptions | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  const market = stringField(data, 'market');
  if (!market) return null;
  return {
    market,
    marketLabel: stringField(data, 'market_label') ?? market,
    deliveryTypes: mapLabeledOptions(data.delivery_types),
    shippingMethods: mapLabeledOptions(data.shipping_methods),
  };
}

export function mapDeliveryOptionShow(raw: unknown): DeliveryOptionShow {
  const data = asRecord(raw);
  return {
    deliveryOption: mapDeliveryOptionSnapshot(data.delivery_option),
    available: mapDeliveryAvailableOptions(data.available),
  };
}

export function buildSelectDeliveryOptionPayload(input: {
  deliveryType: string;
  shippingMethod?: string | null;
  agentName?: string | null;
  agentContact?: string | null;
  notes?: string | null;
}): Record<string, string | null> {
  return {
    delivery_type: input.deliveryType,
    shipping_method: input.shippingMethod?.trim() || null,
    agent_name: input.agentName?.trim() || null,
    agent_contact: input.agentContact?.trim() || null,
    notes: input.notes?.trim() || null,
  };
}

export function buildUpdateDeliveryOptionPayload(input: {
  deliveryType?: string;
  shippingMethod?: string | null;
  agentName?: string | null;
  agentContact?: string | null;
  notes?: string | null;
  deliveryStatus?: string;
}): Record<string, string | null> {
  const body: Record<string, string | null> = {};
  if (input.deliveryType) body.delivery_type = input.deliveryType;
  if (input.shippingMethod !== undefined) {
    body.shipping_method = input.shippingMethod?.trim() || null;
  }
  if (input.agentName !== undefined) {
    body.agent_name = input.agentName?.trim() || null;
  }
  if (input.agentContact !== undefined) {
    body.agent_contact = input.agentContact?.trim() || null;
  }
  if (input.notes !== undefined) {
    body.notes = input.notes?.trim() || null;
  }
  if (input.deliveryStatus) body.delivery_status = input.deliveryStatus;
  return body;
}

/**
 * Web OrderDetailsContent canSelect contract, plus terminal-order safety.
 * Backend still authorizes POST/PATCH.
 */
export function canManagePostPayDeliveryOption(input: {
  status: string | null | undefined;
  paymentStatus: string | null | undefined;
}): boolean {
  const status = input.status?.trim().toLowerCase() ?? '';
  if (status === 'cancelled' || status === 'refunded' || status === 'refund_pending') {
    return false;
  }
  const payment = input.paymentStatus?.trim().toLowerCase() ?? '';
  return (
    payment === 'paid' ||
    status === 'confirmed' ||
    status === 'processing' ||
    status === 'shipped' ||
    status === 'paid'
  );
}

export function isDeliveryOptionLocked(option: DeliveryOptionSnapshot | null): boolean {
  return option?.deliveryStatus === 'completed';
}
