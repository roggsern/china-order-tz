import type { CartLineItem, CartState, CartTotals } from "@/lib/types/cart";
import type { Product, ProductVariantChoice } from "@/lib/types/catalog";
import {
  createCartItemId,
  getSelectedSize,
  hasSizeVariants,
  normalizeVariantChoice,
  variantKey,
} from "@/lib/catalog/variants";
import { getProductPrimaryImage } from "@/lib/catalog/product-images";
import { calculateOrderSummary } from "@/lib/shipping/smart-engine";

export { createCartItemId };

export function createSavedItemId(productId: number, variant: ProductVariantChoice = {}): string {
  const key = variantKey(variant);
  return key === "default" ? `saved-item-${productId}` : `saved-item-${productId}-${key}`;
}

export function productToCartSnapshot(
  product: Product,
  options?: {
    variant?: ProductVariantChoice;
  },
) {
  const primaryImage = getProductPrimaryImage(product);
  const variant = normalizeVariantChoice(options?.variant);
  const selectedSize = hasSizeVariants(product) ? getSelectedSize(variant) : null;

  return {
    productId: product.id,
    slug: product.slug,
    name: product.name,
    unitPrice: product.price,
    origin: product.origin,
    brand: product.brand,
    categorySlug: product.categorySlug,
    weightKg: product.weightKg,
    airCost: product.airCost,
    seaCost: product.seaCost,
    airDeliveryDays: product.airDeliveryDays,
    seaDeliveryDays: product.seaDeliveryDays,
    image: {
      id: primaryImage.id,
      emoji: primaryImage.emoji,
      gradient: primaryImage.gradient,
      alt: primaryImage.alt,
      url: primaryImage.url,
    },
    stock: product.stock,
    selectedSize,
    variant,
    shippingOptions: product.shippingOptions,
  };
}

export function calculateCartTotals(state: CartState): CartTotals {
  const itemCount = state.items.reduce((sum, item) => sum + item.quantity, 0);
  const summary = calculateOrderSummary(state.items, state.discount);

  return {
    itemCount,
    uniqueItemCount: state.items.length,
    productTotal: summary.productTotal,
    shippingTotal: summary.shippingTotal,
    discount: summary.discount,
    grandTotal: summary.grandTotal,
  };
}

export function clampQuantity(quantity: number, stock: number): number {
  const max = Math.max(0, Math.min(stock, 99));
  return Math.max(1, Math.min(max, quantity));
}

export function getLineTotal(item: CartLineItem): number {
  return item.unitPrice * item.quantity;
}

export function isProductInCart(
  state: CartState,
  productId: number,
  variant?: ProductVariantChoice,
): boolean {
  const key = variantKey(variant);
  return state.items.some(
    (item) => item.productId === productId && variantKey(item.variant) === key,
  );
}

export function cartItemsMatch(
  a: Pick<CartLineItem, "productId" | "variant">,
  b: Pick<CartLineItem, "productId" | "variant">,
): boolean {
  return a.productId === b.productId && variantKey(a.variant) === variantKey(b.variant);
}
