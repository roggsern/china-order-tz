import { apiClient } from '@/src/core/api';
import { ApiError } from '@/src/core/errors';

export type WishlistItem = {
  id: string;
  productId: string;
  productVariantId: string | null;
  productSlug: string | null;
  productName: string | null;
  createdAt: string | null;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function mapWishlistItem(raw: unknown): WishlistItem | null {
  const data = asRecord(raw);
  const id =
    typeof data.id === 'string' || typeof data.id === 'number'
      ? String(data.id)
      : '';
  const productId =
    typeof data.product_id === 'string' || typeof data.product_id === 'number'
      ? String(data.product_id)
      : '';
  if (!id || !productId) return null;
  const product = asRecord(data.product);
  return {
    id,
    productId,
    productVariantId:
      typeof data.product_variant_id === 'string'
        ? data.product_variant_id
        : null,
    productSlug: stringField(product, 'slug'),
    productName: stringField(product, 'name'),
    createdAt: stringField(data, 'created_at'),
  };
}

export async function fetchWishlist(): Promise<WishlistItem[]> {
  const response = await apiClient.get<unknown>('/wishlist');
  const rows = Array.isArray(response.data) ? response.data : [];
  return rows
    .map(mapWishlistItem)
    .filter((row): row is WishlistItem => row !== null);
}

export async function addWishlistItem(params: {
  productId: string;
  productVariantId?: string | null;
}): Promise<WishlistItem | null> {
  const response = await apiClient.post<unknown>('/wishlist/items', {
    product_id: params.productId,
    product_variant_id: params.productVariantId ?? null,
  });
  return mapWishlistItem(response.data);
}

export async function removeWishlistItem(productId: string): Promise<void> {
  await apiClient.delete(`/wishlist/items/${encodeURIComponent(productId)}`);
}

export type PublicFeatures = {
  wishlist: boolean;
};

export async function fetchPublicFeatures(): Promise<PublicFeatures> {
  try {
    const response = await apiClient.get<unknown>('/features/public', undefined, null);
    const data = asRecord(response.data);
    return {
      wishlist: data.wishlist === true || data.wishlist === 1 || data.wishlist === '1',
    };
  } catch (error) {
    if (error instanceof ApiError) {
      return { wishlist: true };
    }
    return { wishlist: true };
  }
}
