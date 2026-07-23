import type { ProductOrigin } from "@/lib/types/catalog";

export type { ProductOrigin } from "@/lib/types/catalog";

/**
 * @deprecated Ambiguous name. Prefer ProductOrigin ("china" | "tz").
 * Historically this UI label used "local" as a display alias for origin "tz".
 * Do not confuse with CatalogProductType or Configuration Template (ADR 052).
 */
export type ProductType = "china" | "local";

/**
 * UI / order-list commerce source label. "local" === ProductOrigin "tz".
 * Not a shipping method and not a Catalog Product Type.
 */
export type CommerceSourceLabel = "china" | "local";

export const LOCAL_DELIVERY_NEGOTIATED_LABEL = "To be negotiated before delivery";

/** Map canonical ProductOrigin to UI commerce source label. */
export function originToCommerceSourceLabel(origin: ProductOrigin): CommerceSourceLabel {
  return origin === "tz" ? "local" : "china";
}

/** Map UI commerce source label (incl. legacy "local") to ProductOrigin. */
export function commerceSourceLabelToOrigin(label: CommerceSourceLabel | ProductOrigin): ProductOrigin {
  if (label === "tz" || label === "local") {
    return "tz";
  }
  return "china";
}

/**
 * @deprecated Use originToCommerceSourceLabel.
 * Returns "local" for tz origin (UI alias), not a DB Product Type entity.
 */
export function getProductType(origin: ProductOrigin): ProductType {
  return originToCommerceSourceLabel(origin);
}

/**
 * @deprecated Prefer product.origin (ProductOrigin) directly.
 */
export function resolveProductType(product: {
  type?: ProductType | CommerceSourceLabel;
  origin: ProductOrigin;
}): ProductType {
  if (product.type === "local" || product.type === "china") {
    return product.type;
  }
  return originToCommerceSourceLabel(product.origin);
}

/**
 * @deprecated Use commerceSourceLabelToOrigin.
 */
export function productTypeToOrigin(type: ProductType | CommerceSourceLabel): ProductOrigin {
  return commerceSourceLabelToOrigin(type);
}

/** @deprecated Prefer origin === "china". */
export function isChinaProductType(type: ProductType | CommerceSourceLabel): boolean {
  return type === "china";
}

/** @deprecated Prefer origin === "tz". "local" is the UI alias for tz. */
export function isLocalProductType(type: ProductType | CommerceSourceLabel): boolean {
  return type === "local";
}

export function isLocalProduct(origin: ProductOrigin): boolean {
  return origin === "tz";
}

/**
 * Shipping option type "local" means local delivery method — not ProductOrigin.
 */
export function isNegotiatedLocalDelivery(input: {
  origin?: ProductOrigin;
  shippingOptionType?: "air" | "sea" | "local";
}): boolean {
  if (input.shippingOptionType === "local") {
    return true;
  }
  return input.origin !== undefined && isLocalProduct(input.origin);
}
