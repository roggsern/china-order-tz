import { buildCatalogProductShowBffPath } from "@/lib/api/catalog-proxy";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { normalizePhoneToE164 } from "@/lib/phone";
import type { CartLineItem, CartState, CartTotals } from "@/lib/types/cart";
import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import {
  createOrderFromCheckoutSession,
  CustomerOrdersApiError,
  type OrderEngineCreatedOrder,
} from "@/lib/api/customer-orders";
import {
  CheckoutSessionApiError,
  applyCheckoutShippingChoice,
  startCheckoutSession,
} from "@/lib/api/customer-checkout-session";
import type { CheckoutShippingChoice } from "@/lib/checkout/shipping-choice";
import { toApiShippingMethod } from "@/lib/checkout/shipping-choice";
import { inferProductOrigin } from "@/lib/catalog/map-api-product";
import { loadVisitorIdentity } from "@/lib/storefront/visitor-identity";
import type { ProductOrigin } from "@/lib/types/catalog";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type BackendCheckoutSummary = {
  subtotal: string;
  shipping: string;
  discount: string;
  total: string;
};

export type BackendOrderConfirmation = {
  order: {
    id: string;
    order_number: string;
    status: string;
    placed_at: string;
  };
  items: Array<{
    id: string;
    product_id: string;
    product_name: string;
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
    shipping_method?: string;
    shipping_price?: string | number;
    shipping_subtotal?: string | number;
  }>;
  summary: BackendCheckoutSummary;
};

export class CustomerCheckoutApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerCheckoutApiError";
  }
}

function getAuthHeaders(token?: string | null): HeadersInit {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CustomerCheckoutApiError(
      "You're almost there! Sign in or create your account to complete your order. Your cart has already been saved.",
      401,
    );
  }

  return {
    Accept: "application/json",
    Authorization: `Bearer ${authToken}`,
    "Content-Type": "application/json",
  };
}

function formatApiErrorMessage(
  payload: ApiSuccessResponse<unknown>,
  fallback: string,
): string {
  if (payload.message?.trim()) {
    return payload.message.trim();
  }

  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) {
      return first.trim();
    }
  }

  return fallback;
}

async function customerApiFetch<T>(
  path: string,
  init: RequestInit,
  fallbackError: string,
): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;

  if (!response.ok || payload.success === false) {
    throw new CustomerCheckoutApiError(
      formatApiErrorMessage(payload, fallbackError),
      response.status,
    );
  }

  return payload.data as T;
}

function hasCollectableCheckoutPhone(customer: CustomerInformation): boolean {
  return Boolean(customer.phone.trim());
}

function shouldSendChinaShippingMethod(
  item: CartLineItem,
  requiresChinaShipping: boolean,
): boolean {
  if (!requiresChinaShipping) {
    return false;
  }

  return item.shippingMethod === "air_freight" || item.shippingMethod === "sea_freight";
}

/** Resolve checkout sync shipping requirements using commerce channel as authoritative. */
export function resolveCheckoutSyncProductResolution(input: {
  productId: string;
  commerceChannelCode?: string | null;
  commerceSourceLabel?: string | null;
  origin?: ProductOrigin;
  requiresChinaShipping?: boolean;
  airCost?: number;
  seaCost?: number;
}): { productId: string; requiresChinaShipping: boolean } {
  return {
    productId: input.productId.trim(),
    requiresChinaShipping:
      inferProductOrigin({
        commerceChannelCode: input.commerceChannelCode,
        commerceSourceLabel: input.commerceSourceLabel,
        origin: input.origin,
        requiresChinaShipping: input.requiresChinaShipping,
        shippingAir: input.airCost,
        shippingSea: input.seaCost,
      }) === "china",
  };
}

async function resolveCatalogProductForSync(item: CartLineItem): Promise<{
  productId: string;
  requiresChinaShipping: boolean;
}> {
  if (item.catalogProductId?.trim()) {
    return resolveCheckoutSyncProductResolution({
      productId: item.catalogProductId,
      origin: item.origin,
      airCost: item.airCost,
      seaCost: item.seaCost,
    });
  }

  const response = await fetch(buildCatalogProductShowBffPath(item.slug), {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = (await response.json()) as ApiSuccessResponse<{
    id?: string;
    commerce_channel_code?: string | null;
    commerce_source_label?: string | null;
    requires_china_shipping?: boolean;
    shipping_prices?: { air?: string | number | null; sea?: string | number | null };
  }>;

  if (!response.ok || !payload.data?.id) {
    throw new CustomerCheckoutApiError(
      `Unable to sync "${item.name}" with the server. Please remove it and add it again from the catalog.`,
      response.status,
    );
  }

  const air = payload.data.shipping_prices?.air;
  const sea = payload.data.shipping_prices?.sea;
  const parseFreight = (value: string | number | null | undefined): number | undefined => {
    if (value === null || value === undefined || value === "") {
      return undefined;
    }
    const parsed = typeof value === "number" ? value : Number.parseFloat(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined;
  };

  return resolveCheckoutSyncProductResolution({
    productId: payload.data.id,
    commerceChannelCode: payload.data.commerce_channel_code,
    commerceSourceLabel: payload.data.commerce_source_label,
    requiresChinaShipping: payload.data.requires_china_shipping,
    airCost: parseFreight(air),
    seaCost: parseFreight(sea),
  });
}

/**
 * Explicit account/profile name updates only — not used by checkout.
 * Checkout must never send first_name / last_name / name here.
 */
export async function updateCustomerProfile(
  customer: CustomerInformation,
  token?: string | null,
): Promise<void> {
  await customerApiFetch(
    "/api/profile",
    {
      method: "PATCH",
      headers: getAuthHeaders(token),
      body: JSON.stringify({
        first_name: customer.firstName.trim(),
        last_name: customer.lastName.trim(),
        phone: normalizePhoneToE164(customer.phone) ?? customer.phone.trim(),
      }),
    },
    "Unable to save your profile.",
  );
}

/** Phone-only profile sync for checkout — never mutates account identity names. */
export function buildCheckoutPhoneSyncPayload(phone: string): { phone: string } | null {
  const normalized = normalizePhoneToE164(phone) ?? phone.trim();
  if (!normalized) {
    return null;
  }

  return { phone: normalized };
}

export async function syncCustomerCheckoutPhone(
  phone: string,
  token?: string | null,
): Promise<void> {
  const body = buildCheckoutPhoneSyncPayload(phone);
  if (!body) {
    return;
  }

  await customerApiFetch(
    "/api/profile",
    {
      method: "PATCH",
      headers: getAuthHeaders(token),
      body: JSON.stringify(body),
    },
    "Unable to save your phone number.",
  );
}

export async function clearServerCart(token?: string | null): Promise<void> {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CustomerCheckoutApiError(
      "You're almost there! Sign in or create your account to complete your order. Your cart has already been saved.",
      401,
    );
  }

  const response = await fetch("/api/cart/clear", {
    method: "DELETE",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
    },
    cache: "no-store",
  });

  if (!response.ok && response.status !== 404) {
    const payload = (await response.json()) as ApiSuccessResponse<unknown>;
    throw new CustomerCheckoutApiError(
      formatApiErrorMessage(payload, "Unable to sync your cart."),
      response.status,
    );
  }
}

export async function syncCartToServer(
  items: CartLineItem[],
  token?: string | null,
): Promise<void> {
  await clearServerCart(token);

  for (const item of items) {
    const { productId, requiresChinaShipping } = await resolveCatalogProductForSync(item);
    const shippingMethod = shouldSendChinaShippingMethod(item, requiresChinaShipping)
      ? toApiShippingMethod(item.shippingMethod)
      : undefined;

    if (requiresChinaShipping && !shippingMethod) {
      throw new CustomerCheckoutApiError(
        `Select Air Freight or Sea Freight for "${item.name}" before continuing.`,
        422,
      );
    }

    const variantId = item.configurationId?.trim();
    await customerApiFetch(
      "/api/cart/items",
      {
        method: "POST",
        headers: getAuthHeaders(token),
        body: JSON.stringify({
          product_id: productId,
          ...(variantId
            ? {
                product_variant_id: variantId,
                configuration_id: variantId,
              }
            : {}),
          quantity: item.quantity,
          ...(shippingMethod ? { shipping_method: shippingMethod } : {}),
        }),
      },
      `Unable to add "${item.name}" to your server cart.`,
    );
  }
}

/** @deprecated Preview-only. Production order creation uses checkout session + Order Engine. */
export async function prepareCheckout(token?: string | null): Promise<void> {
  await customerApiFetch(
    "/api/checkout/prepare",
    {
      method: "POST",
      headers: getAuthHeaders(token),
    },
    "Checkout could not be prepared. Please review your cart and delivery details.",
  );
}

/**
 * Compatibility: POST /orders/confirm now creates via Checkout Session + Order Engine.
 * Prefer startCheckoutSession + createOrderFromCheckoutSession.
 */
export async function confirmCheckout(
  token?: string | null,
): Promise<BackendOrderConfirmation> {
  return customerApiFetch<BackendOrderConfirmation>(
    "/api/orders/confirm",
    {
      method: "POST",
      headers: getAuthHeaders(token),
    },
    "Unable to create your order. Please try again.",
  );
}

function money(value: string | number | null | undefined): string {
  if (typeof value === "number") {
    return Number.isFinite(value) ? value.toFixed(2) : "0.00";
  }
  if (typeof value === "string" && value.trim()) {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed.toFixed(2) : "0.00";
  }
  return "0.00";
}

/** Maps Order Engine payload into the checkout confirmation shape used by the payment draft. */
export function mapOrderEngineToConfirmation(
  order: OrderEngineCreatedOrder,
): BackendOrderConfirmation {
  const subtotal = money(order.subtotal);
  const shipping = money(order.shipping_total);
  const discount = money(order.discount_total);
  const total = money(order.grand_total ?? order.total);

  return {
    order: {
      id: order.id,
      order_number: order.order_number,
      status: order.status,
      placed_at: order.placed_at ?? new Date().toISOString(),
    },
    items: (order.items ?? []).map((item) => ({
      id: item.id,
      product_id: item.product_id,
      product_name: item.product_name_snapshot ?? item.product_name ?? "Item",
      quantity: item.quantity,
      unit_price: item.unit_price_snapshot ?? item.unit_price ?? 0,
      subtotal: item.line_total ?? item.subtotal ?? item.unit_price ?? 0,
      shipping_method: item.shipping_mode_snapshot,
      shipping_price: item.shipping_price_snapshot,
    })),
    summary: {
      subtotal,
      shipping,
      discount,
      total,
    },
  };
}

export function mapBackendSummaryToTotals(
  summary: BackendCheckoutSummary,
  items: CartLineItem[],
): CartTotals {
  const parse = (value: string | number) => {
    const parsed = typeof value === "number" ? value : Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
  };

  const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);

  return {
    itemCount,
    uniqueItemCount: items.length,
    productTotal: parse(summary.subtotal),
    originalProductTotal: parse(summary.subtotal),
    moqDiscount: 0,
    shippingTotal: parse(summary.shipping),
    discount: parse(summary.discount),
    savings: parse(summary.discount),
    grandTotal: parse(summary.total),
  };
}

export type BackendCheckoutInput = {
  customer: CustomerInformation;
  shippingAddress: ShippingAddress;
  cart: CartState;
  token?: string | null;
  shippingChoice: CheckoutShippingChoice;
  shippingMethod?: ShippingMethodCode | null;
  agentName?: string | null;
  agentContact?: string | null;
};

/**
 * Official production checkout:
 * optional phone sync → sync cart → checkout/start → shipping-choice → orders/from-checkout.
 *
 * Delivery recipient comes from the address book / delivery_addresses sync (set-default),
 * never from account identity names. Checkout does not PATCH first_name / last_name / name.
 */
export async function runBackendCheckoutFlow(
  input: BackendCheckoutInput,
): Promise<BackendOrderConfirmation> {
  if (hasCollectableCheckoutPhone(input.customer)) {
    await syncCustomerCheckoutPhone(input.customer.phone, input.token);
  }

  await syncCartToServer(input.cart.items, input.token);

  try {
    const session = await startCheckoutSession(input.token, loadVisitorIdentity());
    await applyCheckoutShippingChoice(
      session.id,
      {
        shipping_choice: input.shippingChoice,
        shipping_method:
          input.shippingChoice === "company_shipping"
            ? toApiShippingMethod(input.shippingMethod) ?? null
            : null,
        agent_name: input.agentName ?? null,
        agent_contact: input.agentContact ?? null,
      },
      input.token,
    );
    const order = await createOrderFromCheckoutSession(session.id, input.token);
    return mapOrderEngineToConfirmation(order);
  } catch (error) {
    if (error instanceof CustomerCheckoutApiError) {
      throw error;
    }
    const message =
      error instanceof CheckoutSessionApiError || error instanceof CustomerOrdersApiError
        ? error.message
        : error instanceof Error
          ? error.message
          : "Unable to create your order. Please try again.";
    const statusCode =
      error instanceof CheckoutSessionApiError || error instanceof CustomerOrdersApiError
        ? error.statusCode
        : undefined;
    throw new CustomerCheckoutApiError(message, statusCode);
  }
}
