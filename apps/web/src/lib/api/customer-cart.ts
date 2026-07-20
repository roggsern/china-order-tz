import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { applyCartItemShipping } from "@/lib/cart/shipping";
import type { CartLineItem } from "@/lib/types/cart";
import type { ProductOrigin } from "@/lib/types/catalog";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
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
  product?: {
    id: string;
    name?: string;
    slug?: string;
    air_shipping_price?: string | number | null;
    sea_shipping_price?: string | number | null;
    brand?: { name?: string; slug?: string } | null;
    category?: { slug?: string } | null;
    images?: Array<{
      id?: string;
      url?: string | null;
      path?: string | null;
      is_primary?: boolean;
      alt?: string | null;
    }>;
  } | null;
  variant?: {
    id: string;
    sku?: string | null;
    name?: string | null;
    attribute_values?: Array<{
      attribute?: { name?: string; slug?: string } | null;
      value?: string | null;
      option?: { label?: string; value?: string } | null;
    }> | null;
  } | null;
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

function resolveOrigin(product: ServerCartItem["product"]): ProductOrigin {
  const air = product?.air_shipping_price;
  const sea = product?.sea_shipping_price;
  const hasFreight =
    (air !== null && air !== undefined && air !== "") ||
    (sea !== null && sea !== undefined && sea !== "");
  return hasFreight ? "china" : "tz";
}

function resolveImage(item: ServerCartItem) {
  const images = item.product?.images ?? [];
  const primary =
    images.find((image) => image.is_primary) ?? images[0] ?? null;

  return {
    id: apiIdToNumericId(primary?.id ?? item.id),
    emoji: "🛒",
    gradient: "from-zinc-100 to-zinc-200",
    alt: primary?.alt ?? item.product?.name ?? "Product",
    url: primary?.url ?? undefined,
    path: primary?.path ?? undefined,
  };
}

function resolveSelectedAttributes(item: ServerCartItem) {
  const values = item.variant?.attribute_values ?? [];
  return values
    .map((row) => {
      const name = row.attribute?.name?.trim();
      const value =
        row.option?.label?.trim() ||
        row.option?.value?.trim() ||
        row.value?.trim() ||
        "";
      if (!name || !value) {
        return null;
      }
      return {
        name,
        value,
        slug: row.attribute?.slug ?? null,
      };
    })
    .filter((row): row is { name: string; value: string; slug: string | null } => row !== null);
}

export function mapServerCartItems(cart: ServerCart): CartLineItem[] {
  return (cart.items ?? [])
    .filter((item) => Boolean(item.product_variant_id))
    .map((item) => {
      const unitPrice = parseMoney(item.price_snapshot ?? item.unit_price);
      const attributes = resolveSelectedAttributes(item);
      const label =
        item.variant?.name?.trim() ||
        attributes.map((row) => row.value).join(" / ") ||
        item.variant?.sku ||
        undefined;

      const base: CartLineItem = {
        id: item.id,
        productId: apiIdToNumericId(item.product_id),
        catalogProductId: item.product_id,
        slug: item.product?.slug ?? item.product_id,
        name: item.product?.name ?? "Product",
        unitPrice,
        origin: resolveOrigin(item.product),
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
        airCost: parseMoney(item.product?.air_shipping_price) || undefined,
        seaCost: parseMoney(item.product?.sea_shipping_price) || undefined,
        quantity: item.quantity,
        addedAt: new Date().toISOString(),
        shippingMethod: "sea_freight",
        unitShippingCost: 0,
        shippingCost: 0,
        estimatedDeliveryDays: "—",
      };

      return applyCartItemShipping(base);
    });
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
    productVariantId: string;
    quantity: number;
    currency?: string;
    shippingMethod?: "air" | "sea";
  },
  token?: string | null,
): Promise<ServerCart> {
  return cartApiFetch<ServerCart>(
    "/api/cart/items",
    {
      method: "POST",
      headers: getAuthHeaders(token),
      body: JSON.stringify({
        product_id: input.productId,
        product_variant_id: input.productVariantId,
        configuration_id: input.productVariantId,
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
