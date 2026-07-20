import type { CartLineItem, CartState, SavedForLaterItem } from "@/lib/types/cart";
import type { Product } from "@/lib/types/catalog";
import {
  canAddProductToCart,
  getProductSizes,
  isValidSizeForProduct,
  normalizeVariantChoice,
} from "@/lib/catalog/variants";
import { clampQuantity, productToCartSnapshot } from "@/lib/cart/utils";
import { applyCartItemShipping } from "@/lib/cart/shipping";

const CHECKOUT_VALIDATION_DEBUG = true;

export type CartValidationLogEntry = {
  cartProductId: number;
  catalogProductId: string | null;
  databaseProductId: string | null;
  productSlug: string;
  productName: string;
  stockQuantity: number | null;
  availabilityStatus: "available" | "unavailable";
  activeStatus: boolean | null;
  validationFailureReason: string | null;
  passed: boolean;
};

type ProductLookup = {
  byNumericId: Map<number, Product>;
  byCatalogId: Map<string, Product>;
  bySlug: Map<string, Product>;
};

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

function buildProductLookup(products: Product[]): ProductLookup {
  const byNumericId = new Map<number, Product>();
  const byCatalogId = new Map<string, Product>();
  const bySlug = new Map<string, Product>();

  for (const product of products) {
    byNumericId.set(product.id, product);

    if (product.catalogProductId) {
      byCatalogId.set(product.catalogProductId, product);
    }

    bySlug.set(product.slug, product);
  }

  return { byNumericId, byCatalogId, bySlug };
}

function resolveProductForCartItem(
  item: Pick<CartLineItem, "productId" | "catalogProductId" | "slug">,
  lookup: ProductLookup,
): Product | undefined {
  return (
    lookup.byNumericId.get(item.productId) ??
    (item.catalogProductId ? lookup.byCatalogId.get(item.catalogProductId) : undefined) ??
    lookup.bySlug.get(item.slug)
  );
}

function logCartValidationCheck(entry: CartValidationLogEntry): void {
  if (!CHECKOUT_VALIDATION_DEBUG) {
    return;
  }

  console.debug("[checkout-validation]", entry);
}

function evaluateCartItem(
  item: CartLineItem | SavedForLaterItem,
  lookup: ProductLookup,
): { product?: Product; failureReason: string | null } {
  const product = resolveProductForCartItem(item, lookup);

  const baseLog: Omit<CartValidationLogEntry, "validationFailureReason" | "passed"> = {
    cartProductId: item.productId,
    catalogProductId: item.catalogProductId ?? product?.catalogProductId ?? null,
    databaseProductId: product?.catalogProductId ?? null,
    productSlug: item.slug,
    productName: item.name,
    stockQuantity: product?.stock ?? null,
    availabilityStatus: "unavailable",
    activeStatus: product?.status === "active" ? true : product ? false : null,
  };

  if (!product) {
    const reason = "Product not found in catalog (ID/slug mismatch with local seed catalog)";
    logCartValidationCheck({
      ...baseLog,
      validationFailureReason: reason,
      passed: false,
    });
    return { failureReason: reason };
  }

  if (product.status !== "active") {
    const reason = `Product status is "${product.status}", expected "active"`;
    logCartValidationCheck({
      ...baseLog,
      productName: product.name,
      validationFailureReason: reason,
      passed: false,
    });
    return { product, failureReason: reason };
  }

  const configurationId = item.configurationId ?? null;
  const lineStock = configurationId ? item.stock : product.stock;

  if (lineStock <= 0) {
    const reason = configurationId
      ? `Configuration out of stock (stock=${lineStock})`
      : `Out of stock (stock=${product.stock})`;
    logCartValidationCheck({
      ...baseLog,
      productName: product.name,
      stockQuantity: lineStock,
      validationFailureReason: reason,
      passed: false,
    });
    return { product, failureReason: reason };
  }

  // Metadata-driven configuration lines skip legacy size/color validation.
  if (configurationId) {
    logCartValidationCheck({
      ...baseLog,
      productName: product.name,
      stockQuantity: lineStock,
      availabilityStatus: "available",
      activeStatus: true,
      validationFailureReason: null,
      passed: true,
    });
    return { product, failureReason: null };
  }

  const variant = sanitizeCartVariant(product, item.variant);
  if (!isCartItemValid(product, variant)) {
    const reason = "Required variant/size selection is missing or invalid";
    logCartValidationCheck({
      ...baseLog,
      productName: product.name,
      stockQuantity: product.stock,
      validationFailureReason: reason,
      passed: false,
    });
    return { product, failureReason: reason };
  }

  logCartValidationCheck({
    ...baseLog,
    productName: product.name,
    stockQuantity: product.stock,
    availabilityStatus: "available",
    activeStatus: true,
    validationFailureReason: null,
    passed: true,
  });

  return { product, failureReason: null };
}

export function validateCartAgainstCatalog(state: CartState, products: Product[]): CartState {
  const lookup = buildProductLookup(products);

  const items = state.items.flatMap((item) => {
    const { product, failureReason } = evaluateCartItem(item, lookup);
    if (!product || failureReason) {
      return [];
    }

    const configurationId = item.configurationId ?? null;
    if (configurationId) {
      return [
        applyCartItemShipping({
          ...item,
          quantity: clampQuantity(item.quantity, item.stock),
          shippingMethod: item.shippingMethod,
        }),
      ];
    }

    const variant = sanitizeCartVariant(product, item.variant);
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
    const { product, failureReason } = evaluateCartItem(item, lookup);
    if (!product || failureReason) {
      return [];
    }

    const configurationId = item.configurationId ?? null;
    if (configurationId) {
      return [item];
    }

    const variant = sanitizeCartVariant(product, item.variant);
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

export function summarizeCartValidationFailures(
  state: CartState,
  products: Product[],
): CartValidationLogEntry[] {
  const lookup = buildProductLookup(products);
  const failures: CartValidationLogEntry[] = [];

  for (const item of state.items) {
    const { failureReason } = evaluateCartItem(item, lookup);
    if (failureReason) {
      const product = resolveProductForCartItem(item, lookup);
      failures.push({
        cartProductId: item.productId,
        catalogProductId: item.catalogProductId ?? product?.catalogProductId ?? null,
        databaseProductId: product?.catalogProductId ?? null,
        productSlug: item.slug,
        productName: item.name,
        stockQuantity: product?.stock ?? null,
        availabilityStatus: "unavailable",
        activeStatus: product?.status === "active" ? true : product ? false : null,
        validationFailureReason: failureReason,
        passed: false,
      });
    }
  }

  return failures;
}
