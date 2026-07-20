import type { ProductOrigin } from "@/lib/types/catalog";
import type { CartState, CartTotals } from "@/lib/types/cart";
import type { ProductVariantChoice } from "@/lib/types/catalog";
import { normalizeSelectedSize } from "@/lib/catalog/variants";
import { deriveUnitShippingCost, reconcileOrderShipping } from "@/lib/shipping/smart-engine";
import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";
import type { PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import type { ProductImage } from "@/lib/types/catalog";
import type { OrderStatusHistoryEntry } from "@/lib/order/tracking-status";
import { ensureStatusHistory } from "@/lib/order/status-history";

export type AdminOrderType = "china" | "dar";

export type AdminOrderListSummary = {
  orderType: AdminOrderType;
  /** API-aligned source: china imports vs local Dar fulfillment */
  source: "china" | "local";
  primaryProductName: string;
  primaryProductImage: ProductImage;
  productNames: string[];
  categorySlugs: string[];
  categoryNames: string[];
  brandSlugs: string[];
  brandNames: string[];
  additionalItemCount: number;
};

export const ORDER_STATUS = {
  PENDING: "pending",
  PENDING_PAYMENT: "pending_payment",
  PAID: "paid",
  CONFIRMED: "confirmed",
  PROCESSING: "processing",
  /** Specialist warehouse label — not a Laravel OrderStatus value */
  PACKED: "packed",
  SHIPPED: "shipped",
  /** Specialist shipment label — not a Laravel OrderStatus value */
  IN_TRANSIT: "in_transit",
  DELIVERED: "delivered",
  COMPLETED: "completed",
  CANCELLED: "cancelled",
  REFUND_PENDING: "refund_pending",
  REFUNDED: "refunded",
} as const;

export type OrderStatus = (typeof ORDER_STATUS)[keyof typeof ORDER_STATUS];

export type OrderItemVariant = {
  size?: string;
  color?: string;
  storage?: string;
  /** @deprecated Use size — kept for cart snapshots that store selectedSize at item root */
  selectedSize?: string;
};

export type OrderItemShipping = {
  method: ShippingMethodCode;
  /** Per-unit shipping cost at checkout */
  unitCost: number;
  /** Total line shipping = unitCost × quantity */
  cost: number;
  days: string;
};

export type ItemShippingBreakdown = {
  itemId: string;
  productId: number;
  productName: string;
  method: ShippingMethodCode;
  methodLabel: string;
  unitCost: number;
  quantity: number;
  totalCost: number;
};

export type OrderConfigurationAttribute = {
  name: string;
  value: string;
  slug?: string | null;
};

export type OrderLineItem = {
  id: string;
  productId: number;
  slug: string;
  name: string;
  /** Unit price at checkout — frozen from cart */
  price: number;
  /** @deprecated Use price — kept for existing UI */
  unitPrice: number;
  /** Pre-MOQ comparison unit price for savings display (frontend only). */
  compareAtUnitPrice?: number;
  quantity: number;
  origin?: ProductOrigin;
  brand?: string;
  brandSlug?: string;
  categorySlug?: string;
  /** Selected size at checkout — frozen from cart; null when not applicable. */
  selectedSize: string | null;
  variant?: OrderItemVariant;
  configurationLabel?: string;
  configurationSku?: string;
  selectedAttributes?: OrderConfigurationAttribute[];
  shipping: OrderItemShipping;
  /** Flat fields for legacy components */
  shippingMethod: ShippingMethodCode;
  shippingCost: number;
  estimatedDeliveryDays: string;
  image: {
    id: number;
    emoji: string;
    gradient: string;
    alt: string;
    url?: string;
  };
};

export type OrderTimelineEvent = {
  id: string;
  title: string;
  description?: string;
  timestamp: string | null;
  state: "completed" | "current" | "upcoming";
};

export type Order = {
  id: string;
  orderNumber: string;
  paymentStatus: PaymentStatus;
  paymentMethod: PaymentMethodCode | null;
  paymentReference: string | null;
  /** Active gateway transaction while M-Pesa STK push is pending */
  paymentTransactionId?: string | null;
  status: OrderStatus;
  createdAt: string;
  updatedAt: string;
  customer: CustomerInformation;
  shippingAddress: ShippingAddress;
  orderNotes: string;
  items: OrderLineItem[];
  /** Frozen cart at checkout initiation */
  cartSnapshot: CartState;
  subtotal: number;
  shippingTotal: number;
  /** Primary method when all lines share one; otherwise first line method */
  shippingMethod: ShippingMethodCode | null;
  itemShippingBreakdown?: ItemShippingBreakdown[];
  grandTotal: number;
  /** Full totals breakdown — derived from frozen snapshot */
  totals: CartTotals;
  timeline: OrderTimelineEvent[];
  /** Logistics status audit trail — source of truth for customer tracking. */
  statusHistory?: OrderStatusHistoryEntry[];
  /** Lightweight list metadata for admin tables — optional, computed when missing. */
  adminListSummary?: AdminOrderListSummary;
};

export type CreateOrderInput = {
  customer: CustomerInformation;
  shippingAddress: ShippingAddress;
  orderNotes: string;
  items: OrderLineItem[];
  totals: CartTotals;
  paymentMethod: PaymentMethodCode;
  cartSnapshot?: CartState;
  /** Frozen shipping snapshot from checkout draft */
  shippingMethod?: ShippingMethodCode | null;
  itemShippingBreakdown?: ItemShippingBreakdown[];
  /** Prevents duplicate orders for the same checkout attempt */
  idempotencyKey?: string;
};

export type FinalizeOrderInput = {
  customer: CustomerInformation;
  shippingAddress: ShippingAddress;
  orderNotes: string;
  paymentMethod: PaymentMethodCode;
};

export function getOrderTotals(order: Order): CartTotals {
  if (order.totals) {
    return order.totals;
  }

  const itemCount = order.items.reduce((sum, item) => sum + item.quantity, 0);

  return {
    itemCount,
    uniqueItemCount: order.items.length,
    productTotal: order.subtotal,
    originalProductTotal: order.subtotal,
    moqDiscount: 0,
    shippingTotal: order.shippingTotal,
    discount: 0,
    savings: 0,
    grandTotal: order.grandTotal,
  };
}

export function normalizeOrderLineItem(raw: OrderLineItem): OrderLineItem {
  const price = raw.price ?? raw.unitPrice;
  const quantity = Math.max(1, raw.quantity);
  const legacyCost = raw.shippingCost ?? raw.shipping?.cost ?? 0;
  const shipping = raw.shipping ?? {
    method: raw.shippingMethod,
    unitCost: deriveUnitShippingCost(legacyCost, quantity),
    cost: legacyCost,
    days: raw.estimatedDeliveryDays,
  };
  const selectedSize = normalizeSelectedSize(
    raw.selectedSize ?? raw.variant?.size ?? raw.variant?.selectedSize,
  );
  const variant = {
    color: raw.variant?.color,
    storage: raw.variant?.storage,
    size: selectedSize ?? undefined,
  };

  return {
    ...raw,
    price,
    unitPrice: raw.unitPrice ?? price,
    selectedSize,
    variant,
    shipping: {
      method: shipping.method ?? raw.shippingMethod,
      unitCost: shipping.unitCost > 0 ? shipping.unitCost : deriveUnitShippingCost(shipping.cost, quantity),
      cost: shipping.cost,
      days: shipping.days ?? raw.estimatedDeliveryDays,
    },
    shippingMethod: raw.shippingMethod ?? shipping.method,
    shippingCost: raw.shippingCost ?? shipping.cost,
    estimatedDeliveryDays: raw.estimatedDeliveryDays ?? shipping.days,
  };
}

export function normalizeOrder(raw: Partial<Order> & Pick<Order, "orderNumber">): Order {
  const items = Array.isArray(raw.items) ? raw.items.map(normalizeOrderLineItem) : [];
  const subtotal = raw.subtotal ?? raw.totals?.productTotal ?? 0;
  const discount = raw.totals?.discount ?? 0;
  const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);

  // Legacy fallback only — stored order snapshots are never recalculated from catalog.
  const legacyShipping = reconcileOrderShipping(items);
  const shippingTotal =
    raw.shippingTotal ?? raw.totals?.shippingTotal ?? legacyShipping.shippingTotal;
  const grandTotal =
    raw.grandTotal ?? raw.totals?.grandTotal ?? Math.max(0, subtotal + shippingTotal - discount);

  const totals: CartTotals = raw.totals
    ? {
        ...raw.totals,
        savings: raw.totals.savings ?? 0,
        moqDiscount: raw.totals.moqDiscount ?? 0,
        originalProductTotal:
          raw.totals.originalProductTotal ??
          raw.totals.productTotal + (raw.totals.moqDiscount ?? 0),
      }
    : {
        itemCount,
        uniqueItemCount: items.length,
        productTotal: subtotal,
        originalProductTotal: subtotal,
        moqDiscount: 0,
        shippingTotal,
        discount,
        savings: 0,
        grandTotal,
      };

  return {
    id: raw.id ?? raw.orderNumber,
    orderNumber: raw.orderNumber,
    paymentStatus: (raw.paymentStatus ?? "pending") as PaymentStatus,
    paymentMethod: raw.paymentMethod ?? null,
    paymentReference: raw.paymentReference ?? null,
    paymentTransactionId: raw.paymentTransactionId ?? null,
    status: raw.status ?? ORDER_STATUS.PENDING,
    createdAt: raw.createdAt ?? new Date().toISOString(),
    updatedAt: raw.updatedAt ?? raw.createdAt ?? new Date().toISOString(),
    customer: raw.customer ?? {
      firstName: "",
      lastName: "",
      email: "",
      phone: "",
    },
    shippingAddress: raw.shippingAddress ?? {
      addressLine1: "",
      addressLine2: "",
      city: "",
      region: "",
      postalCode: "",
      country: "Tanzania",
    },
    orderNotes: raw.orderNotes ?? "",
    items,
    cartSnapshot: raw.cartSnapshot ?? { items: [], savedForLater: [], discount: 0 },
    subtotal,
    shippingTotal: totals.shippingTotal,
    shippingMethod: raw.shippingMethod ?? legacyShipping.shippingMethod,
    itemShippingBreakdown: raw.itemShippingBreakdown ?? legacyShipping.itemShippingBreakdown,
    grandTotal: totals.grandTotal,
    totals: {
      ...totals,
      shippingTotal: totals.shippingTotal,
      grandTotal: totals.grandTotal,
    },
    timeline: raw.timeline ?? [],
    statusHistory: ensureStatusHistory({
      ...(raw as Order),
      timeline: raw.timeline ?? [],
    }),
    adminListSummary: raw.adminListSummary,
  };
}

/** @deprecated Use OrderItemVariant */
export type { ProductVariantChoice };
