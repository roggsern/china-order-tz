import type { Cart } from '../models/types';

/**
 * Authoritative cart only. Never empty the UI from a failed clear.
 */
export function resolveCartAfterClearAttempt(input: {
  previous: Cart;
  serverCart?: Cart | null;
  succeeded: boolean;
}): Cart {
  if (!input.succeeded || !input.serverCart) {
    return input.previous;
  }
  return input.serverCart;
}

export function isCartVisiblyEmpty(cart: Cart | null | undefined): boolean {
  if (!cart) return true;
  return cart.isEmpty || cart.items.length === 0;
}
