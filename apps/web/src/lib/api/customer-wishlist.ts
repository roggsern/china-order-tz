import { getCustomerApiToken } from "@/lib/api/customer-auth";
import type { WishlistItem } from "@/lib/wishlist/storage";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type ServerWishlistItem = {
  id: string;
  product_id: string;
  product_variant_id?: string | null;
  product?: {
    id?: string;
    slug?: string;
    name?: string;
  } | null;
  created_at?: string;
};

export class CustomerWishlistApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerWishlistApiError";
  }
}

function getAuthHeaders(token?: string | null): HeadersInit {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CustomerWishlistApiError("Sign in to sync your wishlist.", 401);
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

async function wishlistApiFetch<T>(
  path: string,
  init: RequestInit,
  fallbackError: string,
): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;

  if (!response.ok || payload.success === false) {
    throw new CustomerWishlistApiError(
      formatApiErrorMessage(payload, fallbackError),
      response.status,
    );
  }

  return payload.data as T;
}

function apiIdToNumericId(id: string): number {
  let hash = 0;
  for (let i = 0; i < id.length; i += 1) {
    hash = (hash * 31 + id.charCodeAt(i)) >>> 0;
  }
  return hash % 2_000_000_000 || 1;
}

export function mapServerWishlistItems(
  items: ServerWishlistItem[],
  metadataByProductId?: Map<string, Partial<WishlistItem>>,
): WishlistItem[] {
  return (items ?? []).map((item) => {
    const productId = item.product_id;
    const meta = metadataByProductId?.get(productId) ?? {};

    return {
      productId: meta.productId ?? apiIdToNumericId(productId),
      catalogProductId: productId,
      slug: meta.slug ?? item.product?.slug ?? productId,
      name: meta.name ?? item.product?.name ?? "Product",
      imageUrl: meta.imageUrl,
      emoji: meta.emoji,
      gradient: meta.gradient,
      price: meta.price,
      addedAt: item.created_at ?? meta.addedAt ?? new Date().toISOString(),
    };
  });
}

export async function fetchServerWishlist(
  token?: string | null,
): Promise<ServerWishlistItem[]> {
  return wishlistApiFetch<ServerWishlistItem[]>(
    "/api/wishlist",
    {
      method: "GET",
      headers: getAuthHeaders(token),
    },
    "Unable to load your wishlist.",
  );
}

export async function addServerWishlistItem(
  input: {
    productId: string;
    productVariantId?: string | null;
  },
  token?: string | null,
): Promise<ServerWishlistItem> {
  const variantId = input.productVariantId?.trim() || null;

  return wishlistApiFetch<ServerWishlistItem>(
    "/api/wishlist/items",
    {
      method: "POST",
      headers: getAuthHeaders(token),
      body: JSON.stringify({
        product_id: input.productId,
        ...(variantId ? { product_variant_id: variantId } : {}),
      }),
    },
    "Unable to add item to your wishlist.",
  );
}

export async function removeServerWishlistItem(
  productId: string,
  token?: string | null,
): Promise<void> {
  await wishlistApiFetch<unknown>(
    `/api/wishlist/items/${encodeURIComponent(productId)}`,
    {
      method: "DELETE",
      headers: getAuthHeaders(token),
    },
    "Unable to remove wishlist item.",
  );
}
