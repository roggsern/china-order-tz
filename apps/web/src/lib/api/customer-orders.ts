import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { resolveImageUrl } from "@/lib/catalog/product-images";
import { getCustomerSession } from "@/lib/customer/session";
import { fetchClientCatalogProducts } from "@/lib/catalog/client-catalog";
import {
  applyResolvedImageToOrderLineItem,
  enrichOrderLineItemsWithCatalogImages,
} from "@/lib/order/resolve-order-item-image";
import {
  mapCustomerProgressToTimelineEvents,
  parseCustomerOrderProgress,
  type CustomerOrderProgress,
} from "@/lib/order/customer-progress";
import { parseReceivingChoiceSnapshot } from "@/lib/api/customer-receiving-choice";
import { productService } from "@/lib/services/product-service.client";
import { durationDaysFromSnapshots } from "@/lib/shipping/durations";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";
import type { Order, OrderLineItem, OrderStatus } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";
import type { PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";

export type ApiCustomerOrderPreview = {
  item_count: number;
  total_quantity: number;
  primary_item: {
    name: string;
    image_url: string | null;
    quantity: number;
  } | null;
  extra_items: number;
};

export type ApiCustomerOrder = {
  id: string;
  order_number: string;
  /** API may return China/Dar or china/local */
  source: string;
  status: string;
  payment_status?: string | null;
  total: number | string;
  created_at: string;
  preview?: ApiCustomerOrderPreview;
};

export type CustomerOrdersListResponse = {
  success: boolean;
  data: ApiCustomerOrder[];
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
  message?: string;
};

export type CustomerOrderListItem = {
  id: string;
  orderNumber: string;
  status: OrderStatus;
  paymentStatus: PaymentStatus;
  createdAt: string;
  grandTotal: number;
  itemPreview: string;
  itemCount: number | null;
  imageUrl?: string;
  /** Raw commerce source from API (China/Dar/china/local). */
  source: string | null;
};

export type CustomerOrdersFetchResult = {
  orders: CustomerOrderListItem[];
  total: number;
};

export class CustomerOrdersApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerOrdersApiError";
  }
}

export class CustomerOrderApiError extends CustomerOrdersApiError {
  constructor(message: string, statusCode?: number) {
    super(message, statusCode);
    this.name = "CustomerOrderApiError";
  }
}

export type ApiCustomerOrderItem = {
  id?: string;
  product_id: number | string;
  product_variant_id?: string | null;
  product_name: string;
  product_name_snapshot?: string | null;
  product_slug_snapshot?: string | null;
  brand_name_snapshot?: string | null;
  variant_name_snapshot?: string | null;
  variant_sku_snapshot?: string | null;
  barcode_snapshot?: string | null;
  sku_snapshot?: string | null;
  currency_snapshot?: string | null;
  unit_price_snapshot?: number | string | null;
  shipping_mode_snapshot?: string | null;
  shipping_price_snapshot?: number | string | null;
  shipping_notes_snapshot?: string | null;
  estimated_min_days_snapshot?: number | string | null;
  estimated_max_days_snapshot?: number | string | null;
  attributes_snapshot?: Array<{ attribute: string; value: string }> | null;
  product_image_snapshot?: string | null;
  image_snapshot?: string | null;
  quantity: number;
  unit_price: number | string;
  line_total?: number | string;
  subtotal: number | string;
  currency?: string;
  shipping_method?: string | null;
  shipping_price?: number | string | null;
  shipping_subtotal?: number | string | null;
  delivery_status?: string | null;
};

export type ApiCustomerOrderShippingAddress = {
  first_name?: string;
  last_name?: string;
  full_name?: string;
  phone?: string;
  email?: string | null;
  address_line_1?: string;
  address_line_2?: string | null;
  city?: string;
  region?: string | null;
  postal_code?: string | null;
  country?: string;
};

export type ApiCustomerOrderPayment = {
  payment_status?: string | null;
  payment_method?: string | null;
  reference?: string | null;
  provider?: string | null;
  amount?: number | string | null;
  currency?: string | null;
  paid_at?: string | null;
  initiated_at?: string | null;
};

export type ApiCustomerOrderDetail = {
  id: string;
  order_number: string;
  source: string;
  status: string;
  created_at: string;
  items: ApiCustomerOrderItem[];
  summary: {
    subtotal: number | string;
    shipping: number | string;
    shipping_total?: number | string;
    tax_total?: number | string;
    discount: number | string;
    discount_total?: number | string;
    grand_total?: number | string;
    total: number | string;
  };
  payment: ApiCustomerOrderPayment;
  shipping_address?: ApiCustomerOrderShippingAddress | null;
  shipment: {
    status?: string | null;
  };
  progress?: CustomerOrderProgress | null;
  receiving_choice?: ReturnType<typeof parseReceivingChoiceSnapshot>;
};

export type CustomerOrderDetailResponse = {
  success: boolean;
  data?: ApiCustomerOrderDetail;
  message?: string;
};

function mapBackendStatuses(status: string): {
  orderStatus: OrderStatus;
  paymentStatus: PaymentStatus;
} {
  switch (status) {
    case "pending":
      return { orderStatus: "pending", paymentStatus: "pending" };
    case "pending_payment":
      return { orderStatus: "pending_payment", paymentStatus: "pending" };
    case "paid":
      return { orderStatus: "paid", paymentStatus: "paid" };
    case "confirmed":
      return { orderStatus: "confirmed", paymentStatus: "paid" };
    case "processing":
      return { orderStatus: "processing", paymentStatus: "paid" };
    case "shipped":
      return { orderStatus: "shipped", paymentStatus: "paid" };
    case "delivered":
      return { orderStatus: "delivered", paymentStatus: "paid" };
    case "completed":
      return { orderStatus: "completed", paymentStatus: "paid" };
    case "cancelled":
      return { orderStatus: "cancelled", paymentStatus: "cancelled" };
    case "refund_pending":
      return { orderStatus: "refund_pending", paymentStatus: "paid" };
    case "refunded":
      return { orderStatus: "refunded", paymentStatus: "refunded" };
    default:
      // Legacy/unknown values: surface safely without inventing progress.
      return { orderStatus: status as OrderStatus, paymentStatus: "pending" };
  }
}

function getSourcePreview(source: ApiCustomerOrder["source"]): string {
  const normalized = String(source || "").trim().toLowerCase();
  if (normalized === "china" || normalized === "china_import") {
    return "Order from China";
  }
  if (
    normalized === "dar" ||
    normalized === "local" ||
    normalized === "tz" ||
    normalized === "tz_local"
  ) {
    return "Buy from TZ";
  }
  return "Order";
}

function parseAmount(value: number | string): number {
  const parsed = typeof value === "number" ? value : Number.parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

export function mapApiPaymentStatus(
  paymentStatus: string | null | undefined,
  orderStatus: string,
): PaymentStatus {
  switch (paymentStatus) {
    case "pending":
    case "initiated":
      return "pending";
    case "paid":
      return "paid";
    case "failed":
    case "expired":
      return "failed";
    case "cancelled":
      return "cancelled";
    case "refunded":
      return "refunded";
    default:
      return mapBackendStatuses(orderStatus).paymentStatus;
  }
}

function mapApiPaymentMethod(method: string | null | undefined): PaymentMethodCode | null {
  if (!method) {
    return null;
  }

  return method as PaymentMethodCode;
}

function mapDetailPaymentMethod(
  payment: ApiCustomerOrderPayment | undefined,
): PaymentMethodCode | null {
  return mapApiPaymentMethod(payment?.payment_method ?? payment?.provider);
}

function customerFromSession(): CustomerInformation {
  const session = getCustomerSession();

  if (!session) {
    return {
      firstName: "",
      lastName: "",
      email: "",
      phone: "",
    };
  }

  const nameParts = session.name?.trim().split(/\s+/).filter(Boolean) ?? [];

  return {
    firstName: nameParts[0] ?? "",
    lastName: nameParts.slice(1).join(" "),
    email: session.email,
    phone: "",
  };
}

function mapApiOrderItem(item: ApiCustomerOrderItem, index: number): OrderLineItem {
  const unitPrice = parseAmount(item.unit_price_snapshot ?? item.unit_price);
  const quantity = Math.max(1, item.quantity);
  const shippingMode = item.shipping_mode_snapshot ?? item.shipping_method;
  const shippingMethod: ShippingMethodCode =
    shippingMode === "air"
      ? "air_freight"
      : shippingMode === "sea"
        ? "sea_freight"
        : item.delivery_status
          ? "local_delivery"
          : "sea_freight";
  const unitShipping = parseAmount(item.shipping_price_snapshot ?? item.shipping_price ?? 0);
  const lineShippingCost =
    item.shipping_subtotal != null
      ? parseAmount(item.shipping_subtotal)
      : unitShipping * quantity;
  const name = item.product_name_snapshot || item.product_name;
  const snapshotUrl = item.product_image_snapshot || item.image_snapshot || undefined;
  const numericProductId =
    typeof item.product_id === "number"
      ? item.product_id
      : Number.parseInt(String(item.product_id).replace(/\D/g, "").slice(0, 9), 10) || index + 1;
  const estimatedDeliveryDays = durationDaysFromSnapshots(
    item.estimated_min_days_snapshot,
    item.estimated_max_days_snapshot,
  );

  const attributeLabel = Array.isArray(item.attributes_snapshot)
    ? item.attributes_snapshot.map((row) => `${row.attribute}: ${row.value}`).join(" · ")
    : undefined;

  return applyResolvedImageToOrderLineItem({
    id: item.id?.trim() || `${item.product_id}-${index}`,
    productId: numericProductId,
    slug: item.product_slug_snapshot || String(item.product_id),
    name,
    configurationLabel:
      item.variant_name_snapshot || attributeLabel || undefined,
    configurationSku: item.variant_sku_snapshot || item.sku_snapshot || undefined,
    price: unitPrice,
    unitPrice,
    quantity,
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
    },
  });
}

function mapApiShippingAddressToUi(
  address: ApiCustomerOrderShippingAddress | null | undefined,
): ShippingAddress {
  if (!address) {
    return {
      addressLine1: "",
      addressLine2: "",
      city: "",
      region: "",
      postalCode: "",
      country: "Tanzania",
    };
  }

  return {
    addressLine1: address.address_line_1?.trim() ?? "",
    addressLine2: address.address_line_2?.trim() ?? "",
    city: address.city?.trim() ?? "",
    region: address.region?.trim() ?? "",
    postalCode: address.postal_code?.trim() ?? "",
    country: address.country?.trim() || "Tanzania",
  };
}

export function mapApiCustomerOrderDetailToOrder(detail: ApiCustomerOrderDetail): Order {
  const items = detail.items.map(mapApiOrderItem);
  const subtotal = parseAmount(detail.summary.subtotal);
  const shippingTotal = parseAmount(detail.summary.shipping);
  const discount = parseAmount(detail.summary.discount);
  const grandTotal = parseAmount(detail.summary.total);
  const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);
  const { orderStatus } = mapBackendStatuses(detail.status);
  const paymentStatus = mapApiPaymentStatus(detail.payment?.payment_status, detail.status);
  const progress = parseCustomerOrderProgress(detail.progress);
  const receivingChoice = parseReceivingChoiceSnapshot(detail.receiving_choice);
  const createdAt = detail.created_at;

  const order = normalizeOrder({
    id: detail.id,
    orderNumber: detail.order_number,
    status: orderStatus,
    paymentStatus,
    paymentMethod: mapDetailPaymentMethod(detail.payment),
    paymentReference: detail.payment?.reference?.trim() || null,
    paymentPaidAt: detail.payment?.paid_at ?? null,
    paymentProvider: detail.payment?.provider?.trim() || null,
    paymentAmount:
      detail.payment?.amount != null && detail.payment.amount !== ""
        ? parseAmount(detail.payment.amount)
        : null,
    paymentCurrency: detail.payment?.currency?.trim() || null,
    createdAt,
    updatedAt: createdAt,
    customer: customerFromSession(),
    shippingAddress: mapApiShippingAddressToUi(detail.shipping_address),
    orderNotes: "",
    items,
    subtotal,
    shippingTotal,
    grandTotal,
    totals: {
      itemCount,
      uniqueItemCount: items.length,
      productTotal: subtotal,
      originalProductTotal: subtotal,
      moqDiscount: 0,
      shippingTotal,
      discount,
      savings: 0,
      grandTotal,
    },
    timeline: [],
    progress,
    receivingChoice,
  });

  const timeline = progress
    ? mapCustomerProgressToTimelineEvents(progress, {
        timestamps: { ORDER_CONFIRMED: createdAt },
      })
    : [];

  return {
    ...order,
    timeline,
  };
}

function resolveListItemPreview(order: ApiCustomerOrder): {
  itemPreview: string;
  itemCount: number | null;
  imageUrl?: string;
} {
  const preview = order.preview;
  const primary = preview?.primary_item;
  const extraItems = preview?.extra_items ?? 0;
  const fallbackPreview = getSourcePreview(order.source);

  if (!primary?.name) {
    return {
      itemPreview: fallbackPreview,
      itemCount: preview?.total_quantity ?? null,
    };
  }

  const snapshotUrl = primary.image_url?.trim();
  const imageUrl = snapshotUrl ? resolveImageUrl(snapshotUrl) : undefined;

  return {
    itemPreview: extraItems > 0 ? `${primary.name} +${extraItems} more` : primary.name,
    itemCount: preview?.total_quantity ?? primary.quantity ?? null,
    imageUrl,
  };
}

export function mapApiCustomerOrderToListItem(order: ApiCustomerOrder): CustomerOrderListItem {
  const { orderStatus } = mapBackendStatuses(order.status);
  const paymentStatus = mapApiPaymentStatus(order.payment_status, order.status);
  const previewFields = resolveListItemPreview(order);

  return {
    id: order.id,
    orderNumber: order.order_number,
    status: orderStatus,
    paymentStatus,
    createdAt: order.created_at,
    grandTotal: parseAmount(order.total),
    itemPreview: previewFields.itemPreview,
    itemCount: previewFields.itemCount,
    imageUrl: previewFields.imageUrl,
    source: order.source ?? null,
  };
}

export async function fetchCustomerOrders(
  options?: {
    page?: number;
    perPage?: number;
    filter?: "all" | "active" | "completed";
    token?: string | null;
  },
): Promise<CustomerOrdersFetchResult> {
  const authToken = options?.token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CustomerOrdersApiError("Sign in to view your order history.", 401);
  }

  const params = new URLSearchParams();
  params.set("page", String(options?.page ?? 1));
  params.set("per_page", String(options?.perPage ?? 50));

  if (options?.filter && options.filter !== "all") {
    params.set("filter", options.filter);
  }

  const response = await fetch(`/api/orders?${params.toString()}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
    },
    cache: "no-store",
  });

  const payload = (await response.json()) as CustomerOrdersListResponse;

  if (!response.ok || payload.success === false) {
    throw new CustomerOrdersApiError(
      payload.message ?? "Unable to load your orders.",
      response.status,
    );
  }

  if (!Array.isArray(payload.data)) {
    throw new CustomerOrdersApiError("Unexpected orders response from the server.");
  }

  return {
    orders: payload.data.map(mapApiCustomerOrderToListItem),
    total: payload.meta?.total ?? payload.data.length,
  };
}

export type OrderEngineCreatedOrder = {
  id: string;
  order_number: string;
  status: string;
  placed_at?: string | null;
  subtotal?: string | number;
  shipping_total?: string | number;
  discount_total?: string | number;
  tax_total?: string | number;
  grand_total: number | string;
  total?: string | number;
  checkout_session_id?: string | null;
  items?: Array<{
    id: string;
    product_id: string;
    product_name_snapshot?: string;
    product_name?: string;
    quantity: number;
    unit_price_snapshot?: string | number;
    unit_price?: string | number;
    line_total?: string | number;
    subtotal?: string | number;
    shipping_mode_snapshot?: string;
    shipping_price_snapshot?: string | number;
  }>;
};

export async function createOrderFromCheckoutSession(
  sessionId: string,
  token?: string | null,
): Promise<OrderEngineCreatedOrder> {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CustomerOrdersApiError("Sign in to create an order.", 401);
  }

  const response = await fetch(
    `/api/orders/from-checkout/${encodeURIComponent(sessionId)}`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${authToken}`,
        "Content-Type": "application/json",
      },
      cache: "no-store",
    },
  );

  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: OrderEngineCreatedOrder;
  };

  if (!response.ok || payload.success === false || !payload.data) {
    throw new CustomerOrdersApiError(
      payload.message ?? "Unable to create order from checkout session.",
      response.status,
    );
  }

  return payload.data;
}

export async function fetchCustomerOrder(
  orderNumber: string,
  token?: string | null,
): Promise<Order> {
  const authToken = token ?? getCustomerApiToken();
  const trimmedOrderNumber = orderNumber.trim();

  if (!authToken) {
    throw new CustomerOrderApiError("Sign in to view your order history.", 401);
  }

  if (!trimmedOrderNumber) {
    throw new CustomerOrderApiError("Order number is required.");
  }

  const response = await fetch(`/api/orders/${encodeURIComponent(trimmedOrderNumber)}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
    },
    cache: "no-store",
  });

  const payload = (await response.json()) as CustomerOrderDetailResponse;

  if (response.status === 404) {
    throw new CustomerOrderApiError(
      payload.message ?? "Order not found.",
      404,
    );
  }

  if (!response.ok || payload.success === false) {
    throw new CustomerOrderApiError(
      payload.message ?? "Unable to load this order.",
      response.status,
    );
  }

  if (!payload.data) {
    throw new CustomerOrderApiError("Unexpected order response from the server.");
  }

  let order = mapApiCustomerOrderDetailToOrder(payload.data);

  const needsCatalogFallback = payload.data.items.some(
    (item) => !(item.product_image_snapshot || item.image_snapshot)?.trim(),
  );

  if (needsCatalogFallback) {
    try {
      let products;
      try {
        products = await fetchClientCatalogProducts();
      } catch {
        products = await productService.list();
      }

      order = {
        ...order,
        items: enrichOrderLineItemsWithCatalogImages(
          order.items,
          products,
          payload.data.items,
        ),
      };
    } catch {
      // Keep snapshot-only resolution when catalog is unavailable.
    }
  }

  return order;
}
