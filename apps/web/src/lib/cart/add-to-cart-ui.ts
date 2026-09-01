import type { PurchaseQuantityBlocker } from "@/lib/purchasing/purchase-quantity";

export type AddToCartResult =
  | {
      ok: true;
      recoveredFromStaleAuth?: boolean;
      purchaseQuantityBlocker?: PurchaseQuantityBlocker | null;
    }
  | { ok: false; message: string };

export const DEFAULT_ADD_TO_CART_FAILURE_MESSAGE = "Unable to add item to your cart.";

/**
 * Await cart write before success UI (toast / drawer).
 */
export async function runAddToCartUi(
  addToCart: () => Promise<AddToCartResult | void>,
  handlers: {
    onSuccess: (result: Extract<AddToCartResult, { ok: true }>) => void;
    onFailure: (message: string) => void;
  },
): Promise<AddToCartResult> {
  const result = (await addToCart()) ?? { ok: true as const };

  if (result.ok) {
    handlers.onSuccess(result);
    return result;
  }

  handlers.onFailure(result.message || DEFAULT_ADD_TO_CART_FAILURE_MESSAGE);
  return result;
}

/**
 * Await cart write before navigating to checkout.
 */
export async function runBuyNowUi(
  addToCart: () => Promise<AddToCartResult | void>,
  handlers: {
    onSuccess: (result: Extract<AddToCartResult, { ok: true }>) => void;
    onFailure: (message: string) => void;
  },
): Promise<AddToCartResult> {
  return runAddToCartUi(addToCart, handlers);
}
