import type {
  CreateReturnInput,
  CustomerReturnRequest,
  CustomerReturnsPage,
  ReturnItem,
  ReturnRefund,
} from '../models/types';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  if (typeof value === 'string' && value.trim() !== '') return value.trim();
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return null;
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

function boolField(data: Record<string, unknown>, key: string): boolean {
  return data[key] === true;
}

function mapReturnItem(raw: unknown): ReturnItem | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const orderItemId = stringField(data, 'order_item_id');
  if (!id || !orderItemId) return null;
  const orderItem = asRecord(data.order_item);
  return {
    id,
    returnRequestId: stringField(data, 'return_request_id'),
    orderItemId,
    quantity: numberField(data, 'quantity') ?? 0,
    reason: stringField(data, 'reason'),
    condition: stringField(data, 'condition'),
    resolution: stringField(data, 'resolution'),
    refundAmount: moneyField(data, 'refund_amount'),
    replacementRequested: boolField(data, 'replacement_requested'),
    productName: stringField(orderItem, 'product_name'),
    orderedQuantity: numberField(orderItem, 'quantity'),
  };
}

function mapReturnRefund(raw: unknown): ReturnRefund | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  if (!id) return null;
  return {
    id,
    amount: moneyField(data, 'amount'),
    currency: stringField(data, 'currency'),
    status: stringField(data, 'status'),
    statusLabel: stringField(data, 'status_label'),
    method: stringField(data, 'method'),
    reference: stringField(data, 'reference'),
    createdAt: stringField(data, 'created_at'),
  };
}

export function mapCustomerReturnRequest(raw: unknown): CustomerReturnRequest | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  if (!id) return null;
  const order = asRecord(data.order);
  const itemsRaw = Array.isArray(data.items) ? data.items : [];
  const refundsRaw = Array.isArray(data.refunds)
    ? data.refunds
    : Array.isArray(data.refund_transactions)
      ? data.refund_transactions
      : [];

  return {
    id,
    orderId: stringField(data, 'order_id') ?? stringField(order, 'id'),
    orderNumber: stringField(order, 'order_number'),
    orderStatus: stringField(order, 'status'),
    status: stringField(data, 'status'),
    reason: stringField(data, 'reason'),
    description: stringField(data, 'description'),
    customerNotes: stringField(data, 'customer_notes'),
    createdAt: stringField(data, 'created_at'),
    updatedAt: stringField(data, 'updated_at'),
    items: itemsRaw
      .map(mapReturnItem)
      .filter((item): item is ReturnItem => item !== null),
    refunds: refundsRaw
      .map(mapReturnRefund)
      .filter((row): row is ReturnRefund => row !== null),
  };
}

export function mapCustomerReturnsPage(envelope: {
  data?: unknown;
  meta?: unknown;
}): CustomerReturnsPage {
  const meta = asRecord(envelope.meta);
  const raw = envelope.data;
  const rows = Array.isArray(raw)
    ? raw
    : raw && typeof raw === 'object' && Array.isArray((raw as { data?: unknown }).data)
      ? ((raw as { data: unknown[] }).data)
      : [];

  const returns = rows
    .map(mapCustomerReturnRequest)
    .filter((row): row is CustomerReturnRequest => row !== null);

  return {
    returns,
    page: numberField(meta, 'current_page') ?? 1,
    lastPage: numberField(meta, 'last_page') ?? 1,
    perPage: numberField(meta, 'per_page') ?? returns.length,
    total: numberField(meta, 'total') ?? returns.length,
  };
}

export function buildCreateReturnPayload(input: CreateReturnInput): {
  reason: string;
  description: string | null;
  customer_notes: string | null;
  items: {
    order_item_id: string;
    quantity: number;
    reason?: string | null;
  }[];
} {
  return {
    reason: input.reason.trim(),
    description: input.description?.trim() || null,
    customer_notes: input.customerNotes?.trim() || null,
    items: input.items.map((item) => ({
      order_item_id: item.orderItemId,
      quantity: item.quantity,
      ...(item.reason ? { reason: item.reason } : {}),
    })),
  };
}
