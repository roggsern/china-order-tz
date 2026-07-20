import type { CartConfigurationAttribute, CartLineItem, CartState, CartTotals } from "@/lib/types/cart";
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
    configurationId?: string | null;
    configurationLabel?: string;
    configurationSku?: string;
    selectedAttributes?: CartConfigurationAttribute[];
    quotedUnitPrice?: number;
    compareAtUnitPrice?: number;
    stockOverride?: number;
  },
) {
  const primaryImage = getProductPrimaryImage(product);
  const variant = normalizeVariantChoice(options?.variant);
  const selectedSize = hasSizeVariants(product) ? getSelectedSize(variant) : null;
  const configurationId = options?.configurationId ?? null;
  const configurationLabel = options?.configurationLabel?.trim() || "";
  const configurationSku = options?.configurationSku?.trim() || "";
  const selectedAttributes = options?.selectedAttributes?.filter(
    (entry) => entry.name.trim() && entry.value.trim(),
  );
  const unitPrice =
    typeof options?.quotedUnitPrice === "number" && Number.isFinite(options.quotedUnitPrice)
      ? options.quotedUnitPrice
      : product.price;
  const compareAtUnitPrice =
    typeof options?.compareAtUnitPrice === "number" &&
    Number.isFinite(options.compareAtUnitPrice) &&
    options.compareAtUnitPrice > unitPrice
      ? options.compareAtUnitPrice
      : undefined;

  return {
    productId: product.id,
    catalogProductId: product.catalogProductId,
    slug: product.slug,
    name: product.name,
    unitPrice,
    compareAtUnitPrice,
    origin: product.origin,
    brand: product.brand,
    brandSlug: product.brandSlug,
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
    stock:
      typeof options?.stockOverride === "number"
        ? options.stockOverride
        : product.stock,
    selectedSize,
    variant,
    configurationId,
    configurationLabel: configurationLabel || undefined,
    configurationSku: configurationSku || undefined,
    selectedAttributes:
      selectedAttributes && selectedAttributes.length > 0
        ? selectedAttributes
        : undefined,
    shippingOptions: product.shippingOptions,
  };
}

export function getLineProductSavings(item: CartLineItem): number {
  const compareAt = item.compareAtUnitPrice;
  if (typeof compareAt !== "number" || !Number.isFinite(compareAt) || compareAt <= item.unitPrice) {
    return 0;
  }
  return (compareAt - item.unitPrice) * item.quantity;
}

export function calculateCartTotals(state: CartState): CartTotals {
  const itemCount = state.items.reduce((sum, item) => sum + item.quantity, 0);
  const summary = calculateOrderSummary(state.items, state.discount);
  const moqDiscount = state.items.reduce((sum, item) => sum + getLineProductSavings(item), 0);

  return {
    itemCount,
    uniqueItemCount: state.items.length,
    productTotal: summary.productTotal,
    originalProductTotal: summary.productTotal + moqDiscount,
    moqDiscount,
    shippingTotal: summary.shippingTotal,
    discount: summary.discount,
    savings: moqDiscount + summary.discount,
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
  configurationId?: string | null,
): boolean {
  return state.items.some((item) =>
    cartItemsMatch(item, { productId, variant, configurationId }),
  );
}

export function cartItemsMatch(
  a: Pick<CartLineItem, "productId" | "variant" | "configurationId">,
  b: Pick<CartLineItem, "productId" | "variant" | "configurationId">,
): boolean {
  const aConfig = a.configurationId ?? null;
  const bConfig = b.configurationId ?? null;

  if (aConfig || bConfig) {
    return a.productId === b.productId && aConfig === bConfig;
  }

  return a.productId === b.productId && variantKey(a.variant) === variantKey(b.variant);
}

export function createConfigurationCartItemId(
  productId: number,
  configurationId: string,
): string {
  return `cart-item-${productId}-cfg-${configurationId}`;
}
