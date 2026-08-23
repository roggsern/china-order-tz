import type {
  OrderDetail,
  OrderDetailItem,
  OrderItemAttribute,
  OrderListItem,
  OrderListPreview,
  OrderPaymentSnapshot,
  ActivePaymentTransactionRef,
  OrderProgress,
  OrderProgressStep,
  OrderShipmentSummary,
  OrderSummary,
  OrderTimelineEvent,
  OrderTracking,
  OrderTrackingShipment,
  OrdersListFilter,
  OrdersListPage,
  ReceivingChoiceSnapshot,
} from '../models/types';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';
import { resolveOrderMediaUrl } from './resolveOrderMediaUrl';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  if (typeof value === 'string' && value.trim() !== '') return value;
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

function boolField(data: Record<string, unknown>, key: string): boolean | null {
  if (typeof data[key] === 'boolean') return data[key] as boolean;
  return null;
}

/**
 * Display-only journey label from server `source` (China / Dar).
 * Does not invent channel rules.
 */
export function journeyLabelFromOrderSource(source: string | null | undefined): string | null {
  if (source === 'China') return 'Order from China';
  if (source === 'Dar') return 'Buy from TZ';
  return source?.trim() ? source : null;
}

export function formatOrderMoney(
  value: string | number | null | undefined,
  currency = 'TZS',
): string {
  return formatCustomerMoney(value, currency);
}

export function mapOrderProgress(raw: unknown): OrderProgress | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  const stepsRaw = Array.isArray(data.steps) ? data.steps : [];
  const steps: OrderProgressStep[] = stepsRaw
    .map((row) => {
      const step = asRecord(row);
      const key = stringField(step, 'key');
      const label = stringField(step, 'label');
      if (!key && !label) return null;
      return {
        key: key ?? '',
        label: label ?? key ?? '',
        completed: step.completed === true,
      };
    })
    .filter((row): row is OrderProgressStep => row !== null);

  return {
    currentKey: stringField(data, 'current_key'),
    currentLabel: stringField(data, 'current_label'),
    steps,
  };
}

function mapListPreview(raw: unknown): OrderListPreview | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  const primaryRaw = data.primary_item ? asRecord(data.primary_item) : null;
  return {
    itemCount: numberField(data, 'item_count') ?? 0,
    totalQuantity: numberField(data, 'total_quantity') ?? 0,
    primaryItem: primaryRaw
      ? {
          name: stringField(primaryRaw, 'name') ?? 'Item',
          imageUrl: resolveOrderMediaUrl(stringField(primaryRaw, 'image_url')),
          quantity: numberField(primaryRaw, 'quantity') ?? 0,
        }
      : null,
    extraItems: numberField(data, 'extra_items') ?? 0,
  };
}

/**
 * Map CustomerOrderResource list card — amounts/status from server only.
 */
export function mapOrderListItem(raw: unknown): OrderListItem | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  if (!id) return null;

  const source = stringField(data, 'source');
  const currency = stringField(data, 'currency') ?? 'TZS';

  return {
    id,
    orderNumber: stringField(data, 'order_number'),
    source,
    journeyLabel: journeyLabelFromOrderSource(source),
    status: stringField(data, 'status'),
    statusLabel: stringField(data, 'status_label'),
    paymentStatus: stringField(data, 'payment_status'),
    currency,
    subtotal: moneyField(data, 'subtotal'),
    grandTotal: moneyField(data, 'grand_total') ?? moneyField(data, 'total'),
    createdAt: stringField(data, 'created_at'),
    preview: mapListPreview(data.preview),
    progress: mapOrderProgress(data.progress),
    canCancel: boolField(data, 'can_cancel'),
    canPay: boolField(data, 'can_pay'),
    activePaymentTransaction: mapActivePaymentTransaction(data.active_payment_transaction),
    receivingChoice: mapReceivingChoiceSnapshot(data.receiving_choice),
  };
}

function resolveLastPage(meta: Record<string, unknown>, page: number, perPage: number, total: number): number {
  const last = numberField(meta, 'last_page');
  if (last != null && last >= 1) return last;
  if (perPage <= 0) return 1;
  return Math.max(1, Math.ceil(total / perPage));
}

/**
 * Map GET /orders envelope (data + meta + optional links).
 */
export function mapOrdersListPage(envelope: {
  data?: unknown;
  meta?: unknown;
  links?: unknown;
}): OrdersListPage {
  const meta = asRecord(envelope.meta);
  const links = asRecord(envelope.links);
  const rows = Array.isArray(envelope.data) ? envelope.data : [];
  const orders = rows
    .map(mapOrderListItem)
    .filter((item): item is OrderListItem => item !== null);

  const page = numberField(meta, 'current_page') ?? 1;
  const perPage = numberField(meta, 'per_page') ?? 10;
  const total = numberField(meta, 'total') ?? orders.length;
  const lastPage = resolveLastPage(meta, page, perPage, total);
  const hasNextFromLinks =
    typeof links.next === 'string' && links.next.trim() !== '';
  const hasNextPage = hasNextFromLinks || page < lastPage;

  return {
    orders,
    page,
    lastPage,
    perPage,
    total,
    hasNextPage,
    nextPage: hasNextPage ? page + 1 : null,
  };
}

export function isOrdersListEmpty(page: OrdersListPage | null | undefined): boolean {
  if (!page) return true;
  return page.total === 0 || page.orders.length === 0;
}

function mapAttributes(raw: unknown): OrderItemAttribute[] {
  if (!Array.isArray(raw)) {
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
      return Object.entries(asRecord(raw))
        .map(([attribute, value]) => {
          if (typeof value !== 'string' && typeof value !== 'number') return null;
          return { attribute, value: String(value) };
        })
        .filter((row): row is OrderItemAttribute => row !== null);
    }
    return [];
  }

  return raw
    .map((row) => {
      const item = asRecord(row);
      const attribute =
        stringField(item, 'attribute') ??
        stringField(item, 'name') ??
        stringField(item, 'key');
      const value =
        stringField(item, 'value') ??
        (typeof item.value === 'number' ? String(item.value) : null);
      if (!attribute || value == null) return null;
      return { attribute, value };
    })
    .filter((row): row is OrderItemAttribute => row !== null);
}

export function mapOrderDetailItem(raw: unknown): OrderDetailItem | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  if (!id) return null;

  return {
    id,
    productId: stringField(data, 'product_id'),
    productVariantId: stringField(data, 'product_variant_id'),
    productName:
      stringField(data, 'product_name') ??
      stringField(data, 'product_name_snapshot') ??
      'Item',
    productSlug: stringField(data, 'product_slug_snapshot'),
    brandName: stringField(data, 'brand_name_snapshot'),
    variantName: stringField(data, 'variant_name_snapshot'),
    variantSku: stringField(data, 'variant_sku_snapshot'),
    sku: stringField(data, 'sku_snapshot'),
    quantity: numberField(data, 'quantity') ?? 0,
    unitPrice:
      moneyField(data, 'unit_price') ?? moneyField(data, 'unit_price_snapshot'),
    lineTotal:
      moneyField(data, 'line_total') ?? moneyField(data, 'subtotal'),
    currency:
      stringField(data, 'currency') ?? stringField(data, 'currency_snapshot'),
    imageUrl: resolveOrderMediaUrl(
      stringField(data, 'product_image_snapshot') ??
        stringField(data, 'image_snapshot'),
    ),
    shippingMethod:
      stringField(data, 'shipping_method') ??
      stringField(data, 'shipping_mode_snapshot'),
    shippingPrice:
      moneyField(data, 'shipping_price') ??
      moneyField(data, 'shipping_price_snapshot'),
    attributes: mapAttributes(data.attributes_snapshot),
    deliveryStatus: stringField(data, 'delivery_status'),
  };
}

function mapOrderSummary(raw: unknown): OrderSummary {
  const data = asRecord(raw);
  return {
    subtotal: moneyField(data, 'subtotal'),
    shipping:
      moneyField(data, 'shipping') ?? moneyField(data, 'shipping_total'),
    tax: moneyField(data, 'tax_total'),
    discount:
      moneyField(data, 'discount') ?? moneyField(data, 'discount_total'),
    grandTotal:
      moneyField(data, 'grand_total') ?? moneyField(data, 'total'),
  };
}

/**
 * Map backend `active_payment_transaction`. Never invent an attempt from local state.
 */
export function mapActivePaymentTransaction(raw: unknown): ActivePaymentTransactionRef | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  if (!id) return null;
  return {
    id,
    status: stringField(data, 'status') ?? '',
    provider: stringField(data, 'provider'),
  };
}

function mapPaymentSnapshot(raw: unknown): OrderPaymentSnapshot | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  return {
    paymentStatus: stringField(data, 'payment_status'),
    paymentMethod: stringField(data, 'payment_method'),
    reference: stringField(data, 'reference'),
    provider: stringField(data, 'provider'),
    amount: moneyField(data, 'amount'),
    currency: stringField(data, 'currency'),
    paidAt: stringField(data, 'paid_at'),
    initiatedAt: stringField(data, 'initiated_at'),
  };
}

function mapDetailShipment(raw: unknown): OrderShipmentSummary | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  return {
    status: stringField(data, 'status'),
    carrierName: stringField(data, 'carrier_name'),
    trackingReference:
      stringField(data, 'tracking_reference') ??
      stringField(data, 'tracking_number'),
    statusLabel: stringField(data, 'status_label'),
  };
}

/** Map CustomerOrderDetailResource. */
export function mapOrderDetail(raw: unknown): OrderDetail {
  const data = asRecord(raw);
  const itemsRaw = Array.isArray(data.items) ? data.items : [];
  const source = stringField(data, 'source');
  const summary = mapOrderSummary(data.summary);
  const payment = mapPaymentSnapshot(data.payment);

  return {
    id: stringField(data, 'id') ?? '',
    orderNumber: stringField(data, 'order_number'),
    source,
    journeyLabel: journeyLabelFromOrderSource(source),
    status: stringField(data, 'status'),
    statusLabel: stringField(data, 'status_label'),
    createdAt: stringField(data, 'created_at'),
    items: itemsRaw
      .map(mapOrderDetailItem)
      .filter((item): item is OrderDetailItem => item !== null),
    summary,
    payment,
    progress: mapOrderProgress(data.progress),
    shipment: mapDetailShipment(data.shipment),
    currency:
      payment?.currency ??
      stringField(data, 'currency') ??
      'TZS',
    canCancel: boolField(data, 'can_cancel'),
    canPay: boolField(data, 'can_pay'),
    activePaymentTransaction: mapActivePaymentTransaction(data.active_payment_transaction),
    receivingChoice: mapReceivingChoiceSnapshot(data.receiving_choice),
  };
}

export function mapReceivingChoiceSnapshot(raw: unknown): ReceivingChoiceSnapshot | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  const selected = stringField(data, 'selected_method');
  return {
    eligible: boolField(data, 'eligible') ?? false,
    canSelect: boolField(data, 'can_select') ?? false,
    selectedMethod:
      selected === 'self_pickup' || selected === 'negotiated_delivery' ? selected : null,
    selectedMethodLabel: stringField(data, 'selected_method_label'),
    selectedAt: stringField(data, 'selected_at'),
  };
}

export function buildReceivingMethodPayload(method: 'self_pickup' | 'negotiated_delivery'): {
  receiving_method: 'self_pickup' | 'negotiated_delivery';
} {
  return { receiving_method: method };
}

export function shouldOfferReceivingChoice(choice: ReceivingChoiceSnapshot | null | undefined): boolean {
  if (!choice) return false;
  return choice.eligible && choice.canSelect && !choice.selectedMethod;
}

const TERMINAL_RECEIVING_ORDER_STATUSES = new Set([
  'cancelled',
  'refunded',
  'refund_pending',
]);

/**
 * Selector visibility. Backend snapshot is authoritative for eligibility;
 * terminal order statuses never keep a receiving action on screen.
 */
export function shouldShowReceivingSelector(
  choice: ReceivingChoiceSnapshot | null | undefined,
  orderStatus?: string | null,
): boolean {
  const status = orderStatus?.trim().toLowerCase() ?? '';
  if (TERMINAL_RECEIVING_ORDER_STATUSES.has(status)) return false;
  return shouldOfferReceivingChoice(choice);
}

function mapTimelineEvent(raw: unknown): OrderTimelineEvent | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  const label =
    stringField(data, 'label') ??
    stringField(data, 'description') ??
    stringField(data, 'step');
  const key = stringField(data, 'key') ?? stringField(data, 'step');
  if (!label && !key) return null;

  return {
    key,
    label,
    description: stringField(data, 'description'),
    completed: data.completed === true,
    completedAt: stringField(data, 'completed_at'),
    step: stringField(data, 'step'),
  };
}

function mapTrackingShipment(raw: unknown): OrderTrackingShipment | null {
  if (!raw || typeof raw !== 'object') return null;
  const data = asRecord(raw);
  return {
    id: stringField(data, 'id'),
    status: stringField(data, 'status'),
    statusLabel: stringField(data, 'status_label'),
    carrierName: stringField(data, 'carrier_name'),
    trackingReference:
      stringField(data, 'tracking_reference') ??
      stringField(data, 'tracking_number'),
    transportModeLabel: stringField(data, 'transport_mode_label'),
  };
}

/** Map ShipmentTrackingResource. */
export function mapOrderTracking(raw: unknown): OrderTracking {
  const data = asRecord(raw);
  const timelineRaw = Array.isArray(data.timeline) ? data.timeline : [];
  const unifiedRaw = Array.isArray(data.unified_timeline)
    ? data.unified_timeline
    : [];

  const shipment =
    mapTrackingShipment(data.shipment) ??
    mapTrackingShipment(data.shipment_summary);

  return {
    orderNumber: stringField(data, 'order_number'),
    currentStatus: stringField(data, 'current_status'),
    currentStatusLabel: stringField(data, 'current_status_label'),
    source: stringField(data, 'source'),
    trackingOwnership: stringField(data, 'tracking_ownership'),
    shipment,
    timeline: timelineRaw
      .map(mapTimelineEvent)
      .filter((event): event is OrderTimelineEvent => event !== null),
    unifiedTimeline: unifiedRaw
      .map(mapTimelineEvent)
      .filter((event): event is OrderTimelineEvent => event !== null),
    progress: mapOrderProgress(data.progress),
    pickup:
      data.pickup && typeof data.pickup === 'object'
        ? (data.pickup as Record<string, unknown>)
        : null,
  };
}

export function normalizeOrdersFilter(value: unknown): OrdersListFilter {
  if (value === 'active' || value === 'completed' || value === 'all') {
    return value;
  }
  return 'all';
}

/**
 * Cancel CTA visibility.
 * Prefer explicit server `can_cancel` when present.
 * Otherwise offer cancel and let the API reject with business_rule_violated —
 * never invent fulfillment / lifecycle eligibility locally.
 */
export function shouldOfferCancel(order: {
  status: string | null;
  canCancel: boolean | null;
}): boolean {
  if (typeof order.canCancel === 'boolean') {
    return order.canCancel;
  }
  // Already cancelled per server status — no point offering again.
  return order.status !== 'cancelled';
}

export function buildCancelOrderPayload(reason?: string | null): { reason?: string } {
  const trimmed = typeof reason === 'string' ? reason.trim() : '';
  return trimmed ? { reason: trimmed } : {};
}
