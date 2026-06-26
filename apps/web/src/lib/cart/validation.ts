import type { CartState } from "@/lib/types/cart";
import type { Product } from "@/lib/types/catalog";
import {
  canAddProductToCart,
  getProductSizes,
  isValidSizeForProduct,
  normalizeVariantChoice,
} from "@/lib/catalog/variants";
import { clampQuantity, productToCartSnapshot } from "@/lib/cart/utils";
import { applyCartItemShipping } from "@/lib/cart/shipping";

function sanitizeCartVariant(product: Product, variant: ReturnType<typeof normalizeVariantChoice>) {
  const normalized = normalizeVariantChoice(variant);
  if (!normalized) return undefined;

  const sizes = getProductSizes(product);
  if (sizes.length > 0 && !isValidSizeForProduct(product, normalized.size)) {
    return undefined;
  }

  return normalized;
}

function isCartItemValid(product: Product, variant: ReturnType<typeof normalizeVariantChoice>): boolean {
  return canAddProductToCart(product, variant);
}

export function validateCartAgainstCatalog(state: CartState, products: Product[]): CartState {
  const productById = new Map(products.map((product) => [product.id, product]));

  const items = state.items.flatMap((item) => {
    const product = productById.get(item.productId);
    if (!product || product.status !== "active" || product.stock <= 0) {
      return [];
    }

    const variant = sanitizeCartVariant(product, item.variant);
    if (!isCartItemValid(product, variant)) {
      return [];
    }

    const snapshot = productToCartSnapshot(product, { variant });

    return [
      applyCartItemShipping({
        ...item,
        ...snapshot,
        quantity: clampQuantity(item.quantity, product.stock),
        shippingMethod: item.shippingMethod,
      }),
    ];
  });

  const savedForLater = state.savedForLater.flatMap((item) => {
    const product = productById.get(item.productId);
    if (!product || product.status !== "active" || product.stock <= 0) {
      return [];
    }

    const variant = sanitizeCartVariant(product, item.variant);
    if (!isCartItemValid(product, variant)) {
      return [];
    }

    const snapshot = productToCartSnapshot(product, { variant });

    return [
      {
        ...item,
        ...snapshot,
      },
    ];
  });

  return {
    ...state,
    items,
    savedForLater,
  };
}
