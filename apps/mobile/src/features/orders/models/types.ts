import type { ActivePaymentTransactionRef } from '@/src/features/payments/models/types';

export type { ActivePaymentTransactionRef };

/** Server order list filter (Contract v1). */
export type OrdersListFilter = 'all' | 'active' | 'completed';

/** China / Dar source labels from CustomerOrderResource.resolveSource(). */
export type OrderSource = 'China' | 'Dar' | (string & {});

export type OrderProgressStep = {
  key: string;
  label: string;
  completed: boolean;
};

export type OrderProgress = {
  currentKey: string | null;
  currentLabel: string | null;
  steps: OrderProgressStep[];
};

export type OrderListPreviewItem = {
  name: string;
  imageUrl: string | null;
  quantity: number;
};

export type OrderListPreview = {
  itemCount: number;
  totalQuantity: number;
  primaryItem: OrderListPreviewItem | null;
  extraItems: number;
};

/** List card from CustomerOrderResource. */
export type OrderListItem = {
  id: string;
  orderNumber: string | null;
  source: OrderSource | null;
  journeyLabel: string | null;
  status: string | null;
  statusLabel: string | null;
  paymentStatus: string | null;
  currency: string;
  subtotal: string | number | null;
  grandTotal: string | number | null;
  createdAt: string | null;
  preview: OrderListPreview | null;
  progress: OrderProgress | null;
  /** Present only when API includes it — never invent eligibility. */
  canCancel: boolean | null;
  /** Present only when API includes it — never invent eligibility. */
  canPay: boolean | null;
  /** Present only when API includes it — never infer from local storage. */
  activePaymentTransaction: ActivePaymentTransactionRef | null;
};

export type OrdersListPage = {
  orders: OrderListItem[];
  page: number;
  lastPage: number;
  perPage: number;
  total: number;
  hasNextPage: boolean;
  nextPage: number | null;
};

export type OrderItemAttribute = {
  attribute: string;
  value: string;
};

/** Line from CustomerOrderDetailResource items[]. */
export type OrderDetailItem = {
  id: string;
  productId: string | null;
  productVariantId: string | null;
  productName: string;
  productSlug: string | null;
  brandName: string | null;
  variantName: string | null;
  variantSku: string | null;
  sku: string | null;
  quantity: number;
  unitPrice: string | number | null;
  lineTotal: string | number | null;
  currency: string | null;
  imageUrl: string | null;
  shippingMethod: string | null;
  shippingPrice: string | number | null;
  attributes: OrderItemAttribute[];
  deliveryStatus: string | null;
};

export type OrderSummary = {
  subtotal: string | number | null;
  shipping: string | number | null;
  tax: string | number | null;
  discount: string | number | null;
  grandTotal: string | number | null;
};

export type OrderPaymentSnapshot = {
  paymentStatus: string | null;
  paymentMethod: string | null;
  reference: string | null;
  provider: string | null;
  amount: string | number | null;
  currency: string | null;
  paidAt: string | null;
  initiatedAt: string | null;
};

export type OrderShipmentSummary = {
  status: string | null;
  carrierName: string | null;
  trackingReference: string | null;
  statusLabel: string | null;
};

/** Detail from CustomerOrderDetailResource. */
export type OrderDetail = {
  id: string;
  orderNumber: string | null;
  source: OrderSource | null;
  journeyLabel: string | null;
  status: string | null;
  statusLabel: string | null;
  createdAt: string | null;
  items: OrderDetailItem[];
  summary: OrderSummary;
  payment: OrderPaymentSnapshot | null;
  progress: OrderProgress | null;
  shipment: OrderShipmentSummary | null;
  currency: string;
  /** Present only when API includes it. */
  canCancel: boolean | null;
  /** Present only when API includes it — never invent eligibility. */
  canPay: boolean | null;
  /** Present only when API includes it — never infer from local storage. */
  activePaymentTransaction: ActivePaymentTransactionRef | null;
};

export type OrderTimelineEvent = {
  key: string | null;
  label: string | null;
  description: string | null;
  completed: boolean;
  completedAt: string | null;
  step: string | null;
};

export type OrderTrackingShipment = {
  id: string | null;
  status: string | null;
  statusLabel: string | null;
  carrierName: string | null;
  trackingReference: string | null;
  transportModeLabel: string | null;
};

/** Tracking from ShipmentTrackingResource. */
export type OrderTracking = {
  orderNumber: string | null;
  currentStatus: string | null;
  currentStatusLabel: string | null;
  source: string | null;
  trackingOwnership: string | null;
  shipment: OrderTrackingShipment | null;
  timeline: OrderTimelineEvent[];
  unifiedTimeline: OrderTimelineEvent[];
  progress: OrderProgress | null;
  pickup: Record<string, unknown> | null;
};

export type CancelOrderInput = {
  orderId: string;
  reason?: string | null;
};
