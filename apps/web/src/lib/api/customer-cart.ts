import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { applyCartItemShipping } from "@/lib/cart/shipping";
import { resolveCartLineDisplayImage } from "@/lib/catalog/storefront-variant-media";
import {
  formatVariantDisplayLabel,
  mapVariantDisplayAttributes,
  mapVariantDisplayAttributesToSelected,
} from "@/lib/catalog/variant-display-attributes";
import { durationDaysFromSnapshots } from "@/lib/shipping/durations";
import type { CartLineItem } from "@/lib/types/cart";
import type { ProductOrigin } from "@/lib/types/catalog";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { mapVolumePricing, parseVolumeMoney } from "@/lib/pricing/volume-pricing";
import {
  mapPurchaseQuantity,
  mapPurchaseQuantityBlockers,
  type PurchaseQuantityBlocker,
} from "@/lib/purchasing/purchase-quantity";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type ServerCartProduct = {
  id: string;
  name?: string;
  slug?: string;
  commerce_channel_code?: string | null;
  commerce_source_label?: string | null;
  requires_china_shipping?: boolean;
  shipping_prices?: {
    air?: string | number | null;
    sea?: string | number | null;
  };
  /** @deprecated Legacy cart payload field — prefer shipping_prices */
  air_shipping_price?: string | number | null;
  /** @deprecated Legacy cart payload field — prefer shipping_prices */
  sea_shipping_price?: string | number | null;
  brand?: { name?: string; slug?: string } | null;
  category?: { slug?: string } | null;
  primary_image?: {
    id?: string;
    url?: string | null;
    path?: string | null;
    alt_text?: string | null;
  } | null;
  images?: Array<{
    id?: string;
    url?: string | null;
    path?: string | null;
    is_primary?: boolean;
    alt?: string | null;
  }>;
};

export type ServerCartItem = {
  id: string;
  product_id: string;
  product_variant_id: string | null;
  quantity: number;
  unit_price: string | number;
  price_snapshot?: string | number | null;
  currency?: string;
  available_stock?: number | null;
  subtotal?: string | number;
  shipping_method?: string | null;
  shipping_price?: string | number | null;
  estimated_delivery_days?: number | null;
  estimated_min_days?: number | null;
  estimated_max_days?: number | null;
  product?: ServerCartProduct | null;
  variant?: {
    id: string;
    sku?: string | null;
    name?: string | null;
    primary_image?: {
      id?: string;
      url?: string | null;
      path?: string | null;
      alt_text?: string | null;
    } | null;
    images?: Array<{
      id?: string;
      url?: string | null;
      path?: string | null;
      alt_text?: string | null;
    }> | null;
    attribute_values?: Array<{
      attribute?: { name?: string; slug?: string } | null;
      value?: string | null;
      option?: { label?: string; value?: string } | null;
    }> | null;
    display_attributes?: Array<{
      attribute?: string | null;
      value?: string | null;
    }> | null;
  } | null;
  volume_pricing?: unknown;
  purchase_quantity?: unknown;
};

export type ServerCart = {
  id: string;
  status?: string;
  currency?: string;
  items: ServerCartItem[];
  item_count?: number;
  is_empty?: boolean;
  subtotal?: string | number;
  total?: string | number;
  purchase_quantity_blockers?: unknown;
};

export type MappedServerCart = {
  items: CartLineItem[];
  purchaseQuantityBlockers: PurchaseQuantityBlocker[];
};

export class CustomerCartApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerCartApiError";
  }
}

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function isServerCartItemId(id: string): boolean {
  return UUID_RE.test(id);
}

function getAuthHeaders(token?: string | null): HeadersInit {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CustomerCartApiError("Sign in to sync your cart with the server.", 401);
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

async function cartApiFetch<T>(
  path: string,
  init: RequestInit,
  fallbackError: string,
): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;

  if (!response.ok || payload.success === false) {
    throw new CustomerCartApiError(
      formatApiErrorMessage(payload, fallbackError),
      response.status,
    );
  }

  return payload.data as T;
}

function parseMoney(value: string | number | null | undefined): number {
  if (typeof value === "number") {
    return Number.isFinite(value) ? value : 0;
  }
  if (typeof value === "string" && value.trim()) {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }
  return 0;
}

function apiIdToNumericId(id: string): number {
  let hash = 0;
  for (let i = 0; i < id.length; i += 1) {
    hash = (hash * 31 + id.charCodeAt(i)) >>> 0;
  }
  return hash % 2_000_000_000 || 1;
}

function hasPositiveFreightValue(value: string | number | null | undefined): boolean {
  if (value === null || value === undefined || value === "") {
    return false;
  }

  const parsed = typeof value === "number" ? value : Number.parseFloat(value);
  return Number.isFinite(parsed) && parsed > 0;
}

/**
 * Resolve cart line origin from the server cart product contract.
 * Channel code is authoritative; freight fields are legacy fallbacks only.
 */
export function resolveServerCartProductOrigin(
  product: ServerCartProduct | null | undefined,
): ProductOrigin {
  const code = product?.commerce_channel_code?.trim().toUpperCase();
  if (code === "TZ_LOCAL") {
    return "tz";
  }
  if (code === "CHINA_IMPORT") {
    return "china";
  }

  const air = product?.shipping_prices?.air ?? product?.air_shipping_price;
  const sea = product?.shipping_prices?.sea ?? product?.sea_shipping_price;

  if (hasPositiveFreightValue(air) || hasPositiveFreightValue(sea)) {
    return "china";
  }

  return "tz";
}

function resolveShippingCosts(product: ServerCartProduct | null | undefined) {
  const air = product?.shipping_prices?.air ?? product?.air_shipping_price;
  const sea = product?.shipping_prices?.sea ?? product?.sea_shipping_price;

  return {
    airCost: parseMoney(air) || undefined,
    seaCost: parseMoney(sea) || undefined,
  };
}

function resolveImage(item: ServerCartItem) {
  const resolved = resolveCartLineDisplayImage(item, item.id);
  return {
    id: apiIdToNumericId(resolved.id),
    emoji: resolved.emoji,
    gradient: resolved.gradient,
    alt: resolved.alt,
    url: resolved.url,
    path: resolved.path,
  };
}

function resolveSelectedAttributes(item: ServerCartItem) {
  const display = mapVariantDisplayAttributes({
    display_attributes: item.variant?.display_attributes,
    attribute_values: item.variant?.attribute_values,
  });
  return mapVariantDisplayAttributesToSelected(display);
}

export function mapServerCartItems(cart: ServerCart): CartLineItem[] {
  return (cart.items ?? []).map((item) => {
      const unitPrice = parseMoney(item.price_snapshot ?? item.unit_price);
      const volumePricing = mapVolumePricing(item.volume_pricing);
      const compareAt = volumePricing
        ? parseVolumeMoney(volumePricing.base_unit_price)
        : undefined;
      const attributes = resolveSelectedAttributes(item);
      const label =
        item.variant?.name?.trim() ||
        formatVariantDisplayLabel(
          mapVariantDisplayAttributes({
            display_attributes: item.variant?.display_attributes,
            attribute_values: item.variant?.attribute_values,
          }),
        ) ||
        item.variant?.sku ||
        undefined;

      const { airCost, seaCost } = resolveShippingCosts(item.product);
      const serverMethod =
        item.shipping_method === "air"
          ? ("air_freight" as ShippingMethodCode)
          : item.shipping_method === "sea"
            ? ("sea_freight" as ShippingMethodCode)
            : undefined;
      const capturedDays = durationDaysFromSnapshots(
        item.estimated_min_days,
        item.estimated_max_days,
      );

      const base: CartLineItem = {
        id: item.id,
        productId: apiIdToNumericId(item.product_id),
        catalogProductId: item.product_id,
        slug: item.product?.slug ?? item.product_id,
        name: item.product?.name ?? "Product",
        unitPrice,
        compareAtUnitPrice:
          typeof compareAt === "number" && compareAt > unitPrice + 0.001 ? compareAt : undefined,
        volumePricing,
        purchaseQuantity: mapPurchaseQuantity(item.purchase_quantity),
        origin: resolveServerCartProductOrigin(item.product),
        brand: item.product?.brand?.name,
        brandSlug: item.product?.brand?.slug,
        categorySlug: item.product?.category?.slug ?? "uncategorized",
        image: resolveImage(item),
        stock: Math.max(item.available_stock ?? item.quantity, item.quantity),
        selectedSize: null,
        configurationId: item.product_variant_id,
        configurationLabel: label,
        configurationSku: item.variant?.sku ?? undefined,
        selectedAttributes: attributes,
        airCost,
        seaCost,
        quantity: item.quantity,
        addedAt: new Date().toISOString(),
        shippingMethod: serverMethod ?? "sea_freight",
        unitShippingCost: 0,
        shippingCost: 0,
        estimatedDeliveryDays: capturedDays !== "—" ? capturedDays : "—",
      };

      const withShipping = applyCartItemShipping(base);
      if (capturedDays !== "—") {
        withShipping.estimatedDeliveryDays = capturedDays;
      }
      return withShipping;
    });
}

export function mapServerCart(cart: ServerCart): MappedServerCart {
  return {
    items: mapServerCartItems(cart),
    purchaseQuantityBlockers: mapPurchaseQuantityBlockers(cart.purchase_quantity_blockers),
  };
}

export async function fetchServerCart(token?: string | null): Promise<ServerCart> {
  return cartApiFetch<ServerCart>(
    "/api/cart",
    {
      method: "GET",
      headers: getAuthHeaders(token),
    },
    "Unable to load your cart.",
  );
}

export async function addServerCartItem(
  input: {
    productId: string;
    productVariantId?: string | null;
    quantity: number;
    currency?: string;
    shippingMethod?: "air" | "sea";
  },
  token?: string | null,
): Promise<ServerCart> {
  const variantId = input.productVariantId?.trim() || null;

  return cartApiFetch<ServerCart>(
    "/api/cart/items",
    {
      method: "POST",
      headers: getAuthHeaders(token),
      body: JSON.stringify({
        product_id: input.productId,
        ...(variantId
          ? {
              product_variant_id: variantId,
              configuration_id: variantId,
            }
          : {}),
        quantity: input.quantity,
        ...(input.currency ? { currency: input.currency } : {}),
        ...(input.shippingMethod ? { shipping_method: input.shippingMethod } : {}),
      }),
    },
    "Unable to add item to your cart.",
  );
}

export async function updateServerCartItemQuantity(
  itemId: string,
  quantity: number,
  token?: string | null,
): Promise<ServerCart> {
  return cartApiFetch<ServerCart>(
    `/api/cart/items/${encodeURIComponent(itemId)}`,
    {
      method: "PUT",
      headers: getAuthHeaders(token),
      body: JSON.stringify({ quantity }),
    },
    "Unable to update cart quantity.",
  );
}

export async function removeServerCartItem(
  itemId: string,
  token?: string | null,
): Promise<ServerCart> {
  return cartApiFetch<ServerCart>(
    `/api/cart/items/${encodeURIComponent(itemId)}`,
    {
      method: "DELETE",
      headers: getAuthHeaders(token),
    },
    "Unable to remove cart item.",
  );
}

export async function clearServerCartEngine(token?: string | null): Promise<ServerCart> {
  return cartApiFetch<ServerCart>(
    "/api/cart/clear",
    {
      method: "DELETE",
      headers: getAuthHeaders(token),
    },
    "Unable to clear your cart.",
  );
}
