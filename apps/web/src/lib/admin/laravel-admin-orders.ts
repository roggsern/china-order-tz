import type { Order, OrderLineItem, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS, normalizeOrder } from "@/lib/types/order";
import type { PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { backendMethodToStorefrontCode } from "@/lib/checkout/payment-availability";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { durationDaysFromSnapshots } from "@/lib/shipping/durations";
import { EMPTY_SHIPPING_ADDRESS, type ShippingAddress } from "@/lib/types/checkout";
import { applyResolvedImageToOrderLineItem } from "@/lib/order/resolve-order-item-image";

type LaravelAdminShippingAddress = {
  first_name?: string | null;
  last_name?: string | null;
  full_name?: string | null;
  phone?: string | null;
  email?: string | null;
  address_line_1?: string | null;
  address_line_2?: string | null;
  city?: string | null;
  region?: string | null;
  postal_code?: string | null;
  country?: string | null;
};

type LaravelAdminOrderItem = {
  id?: string;
  product_id?: number | string;
  product_name?: string | null;
  product_name_snapshot?: string | null;
  product_slug_snapshot?: string | null;
  variant_name_snapshot?: string | null;
  variant_name?: string | null;
  variant_sku_snapshot?: string | null;
  barcode_snapshot?: string | null;
  sku_snapshot?: string | null;
  brand_name_snapshot?: string | null;
  attributes_snapshot?: Array<{ attribute?: string; value?: string }> | null;
  product_image_snapshot?: string | null;
  image_snapshot?: string | null;
  quantity?: number;
  unit_price?: number | string | null;
  unit_price_snapshot?: number | string | null;
  shipping_method?: string | null;
  shipping_mode_snapshot?: string | null;
  shipping_price?: number | string | null;
  shipping_price_snapshot?: number | string | null;
  shipping_subtotal?: number | string | null;
  estimated_min_days_snapshot?: number | string | null;
  estimated_max_days_snapshot?: number | string | null;
  product?: {
    images?: Array<{ url?: string | null; path?: string | null }> | null;
  } | null;
};

type LaravelAdminOrder = {
  id: string;
  order_number?: string;
  status?: string;
  subtotal?: number | string;
  shipping_total?: number | string;
  shipping_amount?: number | string;
  discount_total?: number | string;
  total?: number | string;
  grand_total?: number | string;
  currency?: string;
  notes?: string | null;
  placed_at?: string | null;
  paid_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  user?: {
    id?: string;
    name?: string;
    email?: string;
    phone?: string | null;
  } | null;
  payments?: Array<{
    status?: string;
    method?: string;
    reference?: string | null;
    paid_at?: string | null;
  }>;
  payment?: {
    payment_status?: string | null;
    payment_method?: string | null;
    provider?: string | null;
    reference?: string | null;
    paid_at?: string | null;
  } | null;
  shipping_address?: LaravelAdminShippingAddress | null;
  items?: LaravelAdminOrderItem[] | null;
};

function asNumber(value: number | string | null | undefined): number {
  if (typeof value === "number") return value;
  if (value == null || value === "") return 0;
  const n = Number.parseFloat(String(value));
  return Number.isFinite(n) ? n : 0;
}

function mapBackendStatus(status: string | undefined): OrderStatus {
  const raw = (status ?? "").trim().toLowerCase();
  const known = Object.values(ORDER_STATUS) as string[];
  if (known.includes(raw)) {
    return raw as OrderStatus;
  }
  return (raw || ORDER_STATUS.PENDING) as OrderStatus;
}

function mapLaravelPaymentMethod(method: string | undefined): PaymentMethodCode | null {
  if (!method?.trim()) {
    return null;
  }

  return backendMethodToStorefrontCode(method.trim().toLowerCase());
}

function mapLaravelShippingAddress(
  raw: LaravelAdminShippingAddress | null | undefined,
): ShippingAddress {
  if (!raw) {
    return { ...EMPTY_SHIPPING_ADDRESS };
  }

  const recipientName =
    raw.full_name?.trim() ||
    [raw.first_name, raw.last_name].filter((part) => part?.trim()).join(" ").trim() ||
    undefined;

  return {
    recipientName,
    phone: raw.phone?.trim() || undefined,
    email: raw.email?.trim() || undefined,
    addressLine1: raw.address_line_1?.trim() ?? "",
    addressLine2: raw.address_line_2?.trim() ?? "",
    city: raw.city?.trim() ?? "",
    region: raw.region?.trim() ?? "",
    postalCode: raw.postal_code?.trim() ?? "",
    country: raw.country?.trim() || EMPTY_SHIPPING_ADDRESS.country,
  };
}

function mapAdminPaymentFields(row: LaravelAdminOrder): {
  paymentMethod: PaymentMethodCode | null;
  paymentProvider: string | null;
  paymentReference: string | null;
  paymentPaidAt: string | null;
} {
  const snapshot = row.payment;
  if (snapshot && typeof snapshot === "object") {
    return {
      paymentMethod: mapLaravelPaymentMethod(snapshot.payment_method ?? undefined),
      paymentProvider: snapshot.provider?.trim() || null,
      paymentReference: snapshot.reference?.trim() || null,
      paymentPaidAt: snapshot.paid_at?.trim() || row.paid_at?.trim() || null,
    };
  }

  const legacy = row.payments?.[0];
  const method = mapLaravelPaymentMethod(legacy?.method);

  return {
    paymentMethod: method,
    paymentProvider: (legacy?.method ?? "").trim().toLowerCase() === "cash" ? "office" : null,
    paymentReference: legacy?.reference?.trim() || null,
    paymentPaidAt: legacy?.paid_at?.trim() || row.paid_at?.trim() || null,
  };
}

function mapPaymentStatus(order: LaravelAdminOrder): PaymentStatus {
  const status = mapBackendStatus(order.status);
  if (status === ORDER_STATUS.REFUNDED) return PAYMENT_STATUS.REFUNDED;
  if (status === ORDER_STATUS.CANCELLED) return PAYMENT_STATUS.CANCELLED;
  if (status === ORDER_STATUS.PENDING || status === ORDER_STATUS.PENDING_PAYMENT) {
    return PAYMENT_STATUS.PENDING;
  }
  if (order.paid_at || status === ORDER_STATUS.PAID || status === ORDER_STATUS.REFUND_PENDING) {
    return PAYMENT_STATUS.PAID;
  }
  const payment = order.payments?.[0];
  if (payment?.status === "paid") return PAYMENT_STATUS.PAID;
  if (payment?.status === "refunded") return PAYMENT_STATUS.REFUNDED;
  return PAYMENT_STATUS.PENDING;
}

function mapShippingMethod(raw: string | null | undefined): ShippingMethodCode {
  const mode = (raw ?? "").trim().toLowerCase();
  if (mode === "air" || mode === "air_freight") return "air_freight";
  if (mode === "local_delivery" || mode === "local") return "local_delivery";
  return "sea_freight";
}

function resolveProductFallbackUrl(product: LaravelAdminOrderItem["product"]): string | undefined {
  const first = product?.images?.[0];
  const candidate = first?.url?.trim() || first?.path?.trim();
  return candidate || undefined;
}

function mapAttributesSnapshot(
  attributes: LaravelAdminOrderItem["attributes_snapshot"],
): OrderLineItem["selectedAttributes"] {
  if (!Array.isArray(attributes)) {
    return undefined;
  }

  const mapped = attributes
    .map((row) => ({
      name: row.attribute?.trim() ?? "",
      value: row.value?.trim() ?? "",
    }))
    .filter((row) => row.name && row.value);

  return mapped.length > 0 ? mapped : undefined;
}

function mapLaravelAdminOrderItem(item: LaravelAdminOrderItem, index: number): OrderLineItem {
  const unitPrice = asNumber(item.unit_price_snapshot ?? item.unit_price);
  const quantity = Math.max(1, item.quantity ?? 1);
  const shippingMethod = mapShippingMethod(item.shipping_mode_snapshot ?? item.shipping_method);
  const unitShipping = asNumber(item.shipping_price_snapshot ?? item.shipping_price ?? 0);
  const lineShippingCost =
    item.shipping_subtotal != null ? asNumber(item.shipping_subtotal) : unitShipping * quantity;
  const name =
    item.product_name_snapshot?.trim() ||
    item.product_name?.trim() ||
    "Product";
  const snapshotUrl = item.product_image_snapshot || item.image_snapshot || undefined;
  const fallbackProductUrl = resolveProductFallbackUrl(item.product);
  const numericProductId =
    typeof item.product_id === "number"
      ? item.product_id
      : Number.parseInt(String(item.product_id ?? "").replace(/\D/g, "").slice(0, 9), 10) ||
        index + 1;
  const attributeLabel = Array.isArray(item.attributes_snapshot)
    ? item.attributes_snapshot
        .map((row) => row.value?.trim())
        .filter(Boolean)
        .join(" • ")
    : undefined;
  const estimatedDeliveryDays = durationDaysFromSnapshots(
    item.estimated_min_days_snapshot,
    item.estimated_max_days_snapshot,
  );

  return applyResolvedImageToOrderLineItem({
    id: item.id?.trim() || `${item.product_id ?? "item"}-${index}`,
    productId: numericProductId,
    slug: item.product_slug_snapshot?.trim() || String(item.product_id ?? numericProductId),
    name,
    brand: item.brand_name_snapshot?.trim() || undefined,
    configurationLabel:
      item.variant_name_snapshot?.trim() ||
      item.variant_name?.trim() ||
      attributeLabel ||
      undefined,
    configurationSku: item.variant_sku_snapshot?.trim() || item.sku_snapshot?.trim() || undefined,
    selectedAttributes: mapAttributesSnapshot(item.attributes_snapshot),
    price: unitPrice,
    unitPrice,
    quantity,
    origin: shippingMethod === "local_delivery" ? "tz" : "china",
    selectedSize: null,
    shipping: {
      method: shippingMethod,
      unitCost: unitShipping,
      cost: lineShippingCost,
      days: estimatedDeliveryDays,
    },
    shippingMethod,
    shippingCost: lineShippingCost,
    estimatedDeliveryDays,
    image: {
      id: numericProductId,
      emoji: "📦",
      gradient: "from-zinc-100 to-zinc-200",
      alt: name,
      url: snapshotUrl,
      fallbackProductUrl,
    },
  });
}

export function mapLaravelAdminOrderToWebOrder(row: LaravelAdminOrder): Order {
  const items = Array.isArray(row.items)
    ? row.items.map((item, index) => mapLaravelAdminOrderItem(item, index))
    : [];
  const subtotal = asNumber(row.subtotal);
  const shippingTotal = asNumber(row.shipping_total ?? row.shipping_amount);
  const discount = asNumber(row.discount_total);
  const total = asNumber(row.grand_total ?? row.total);
  const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);
  const createdAt = row.created_at ?? row.placed_at ?? new Date().toISOString();
  const updatedAt = row.updated_at ?? createdAt;
  const customerName = row.user?.name?.trim() || "Customer";
  const [firstName, ...rest] = customerName.split(/\s+/);
  const lastName = rest.join(" ") || "";
  const primaryShippingMethod = items[0]?.shippingMethod ?? null;
  const paymentFields = mapAdminPaymentFields(row);

  return normalizeOrder({
    id: row.id,
    orderNumber: row.order_number ?? row.id,
    paymentStatus: mapPaymentStatus(row),
    paymentMethod: paymentFields.paymentMethod,
    paymentProvider: paymentFields.paymentProvider,
    paymentReference: paymentFields.paymentReference,
    paymentPaidAt: paymentFields.paymentPaidAt,
    status: mapBackendStatus(row.status),
    createdAt,
    updatedAt,
    customer: {
      firstName: firstName || "Customer",
      lastName,
      email: row.user?.email ?? "",
      phone: row.user?.phone ?? "",
    },
    shippingAddress: mapLaravelShippingAddress(row.shipping_address),
    orderNotes: row.notes ?? "",
    items,
    cartSnapshot: {
      items: [],
      savedForLater: [],
      discount: 0,
    },
    subtotal: subtotal || total,
    shippingTotal,
    shippingMethod: primaryShippingMethod,
    grandTotal: total,
    totals: {
      itemCount,
      uniqueItemCount: items.length,
      productTotal: subtotal || total,
      originalProductTotal: subtotal || total,
      moqDiscount: 0,
      shippingTotal,
      discount,
      savings: 0,
      grandTotal: total,
    },
    timeline: [],
  });
}

export function mapLaravelOrdersPayloadToAdminOrders(payload: unknown): Order[] {
  if (!payload || typeof payload !== "object") {
    return [];
  }

  const root = payload as {
    data?: unknown;
    orders?: unknown;
  };

  let rows: unknown = root.data ?? root.orders ?? payload;

  if (rows && typeof rows === "object" && !Array.isArray(rows) && "data" in (rows as object)) {
    rows = (rows as { data: unknown }).data;
  }

  if (!Array.isArray(rows)) {
    return [];
  }

  return rows
    .filter((row): row is LaravelAdminOrder => !!row && typeof row === "object" && "id" in row)
    .map((row) => mapLaravelAdminOrderToWebOrder(row));
}

export function mapLaravelAdminOrderPayloadToWebOrder(payload: unknown): Order | null {
  if (!payload || typeof payload !== "object") {
    return null;
  }

  const root = payload as {
    data?: unknown;
    order?: unknown;
  };

  const candidate = root.data ?? root.order ?? payload;
  if (!candidate || typeof candidate !== "object" || Array.isArray(candidate) || !("id" in candidate)) {
    return null;
  }

  return mapLaravelAdminOrderToWebOrder(candidate as LaravelAdminOrder);
}
