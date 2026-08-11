import { apiClient } from '@/src/core/api';
import type { AddToCartInput, Cart } from '../models/types';
import {
  buildAddToCartPayload,
  buildUpdateCartItemPayload,
  mapCart,
} from '../utils/mapCart';

/**
 * POST /cart/items — authenticated Contract v1 (no guest cart).
 */
export async function addToCart(input: AddToCartInput): Promise<Cart> {
  const body = buildAddToCartPayload(input);
  const response = await apiClient.post<unknown>('/cart/items', body);
  return mapCart(response.data);
}

/**
 * GET /cart — authenticated Contract v1.
 */
export async function fetchCart(): Promise<Cart> {
  const response = await apiClient.get<unknown>('/cart');
  return mapCart(response.data);
}

/**
 * PATCH /cart/items/{item} — quantity update; server returns full cart.
 */
export async function updateCartItemQuantity(
  itemId: string,
  quantity: number,
): Promise<Cart> {
  const response = await apiClient.patch<unknown>(
    `/cart/items/${encodeURIComponent(itemId)}`,
    buildUpdateCartItemPayload(quantity),
  );
  return mapCart(response.data);
}

/**
 * DELETE /cart/items/{item} — remove line; server returns full cart.
 */
export async function removeCartItem(itemId: string): Promise<Cart> {
  const response = await apiClient.delete<unknown>(
    `/cart/items/${encodeURIComponent(itemId)}`,
  );
  return mapCart(response.data);
}
