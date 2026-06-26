import type { ProductOrigin } from "@/lib/types/catalog";
import type { CartState, CartTotals } from "@/lib/types/cart";
import type { ProductVariantChoice } from "@/lib/types/catalog";
import { normalizeSelectedSize } from "@/lib/catalog/variants";
import { deriveUnitShippingCost, reconcileOrderShipping } from "@/lib/shipping/smart-engine";
import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";
import type { PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";
import type { ShippingMethodCode } from "@/lib/shipping/types";

export const ORDER_STATUS = {
  PENDING: "pending",
  /** @deprecated Use PENDING — kept for legacy stored orders */
  PENDING_PAYMENT: "pending_payment",
  CONFIRMED: "confirmed",
  PROCESSING: "processing",
  SHIPPED: "shipped",
  DELIVERED: "delivered",
  CANCELLED: "cancelled",
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

export type OrderLineItem = {
  id: string;
  productId: number;
  slug: string;
  name: string;
  /** Unit price at checkout — frozen from cart */
  price: number;
  /** @deprecated Use price — kept for existing UI */
  unitPrice: number;
  quantity: number;
  origin?: ProductOrigin;
  /** Selected size at checkout — frozen from cart; null when not applicable. */
  selectedSize: string | null;
  variant?: OrderItemVariant;
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
    shippingTotal: order.shippingTotal,
    discount: 0,
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

  const totals: CartTotals = raw.totals ?? {
    itemCount,
    uniqueItemCount: items.length,
    productTotal: subtotal,
    shippingTotal,
    discount,
    grandTotal,
  };

  return {
    id: raw.id ?? raw.orderNumber,
    orderNumber: raw.orderNumber,
    paymentStatus: (raw.paymentStatus ?? "pending") as PaymentStatus,
    paymentMethod: raw.paymentMethod ?? null,
    paymentReference: raw.paymentReference ?? null,
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
  };
}

/** @deprecated Use OrderItemVariant */
export type { ProductVariantChoice };
