import type { ProductOrigin } from "@/lib/types/catalog";
import type { ProductType } from "@/lib/types/catalog";

export type { ProductType } from "@/lib/types/catalog";

export const LOCAL_DELIVERY_NEGOTIATED_LABEL = "To be negotiated before delivery";

export function getProductType(origin: ProductOrigin): ProductType {
  return origin === "tz" ? "local" : "china";
}

export function resolveProductType(product: {
  type?: ProductType;
  origin: ProductOrigin;
}): ProductType {
  return product.type ?? getProductType(product.origin);
}

export function productTypeToOrigin(type: ProductType): ProductOrigin {
  return type === "local" ? "tz" : "china";
}

export function isChinaProductType(type: ProductType): boolean {
  return type === "china";
}

export function isLocalProductType(type: ProductType): boolean {
  return type === "local";
}

export function isLocalProduct(origin: ProductOrigin): boolean {
  return getProductType(origin) === "local";
}

export function isNegotiatedLocalDelivery(input: {
  origin?: ProductOrigin;
  shippingOptionType?: "air" | "sea" | "local";
}): boolean {
  if (input.shippingOptionType === "local") {
    return true;
  }
  return input.origin !== undefined && isLocalProduct(input.origin);
}
