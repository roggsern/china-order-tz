export type ProductAvailabilityStatus = "available" | "out_of_stock" | "unavailable";

export type ProductUnavailabilityReason =
  | "missing_inventory_policy"
  | "invalid_pricing"
  | "missing_sellable_variant"
  | "lifecycle_inactive"
  | "missing_shipping_options"
  | "no_purchasable_path"
  | "unavailable";

export function isProductPurchaseUnavailable(product: {
  isPurchasable?: boolean;
  availabilityStatus?: ProductAvailabilityStatus;
}): boolean {
  if (product.availabilityStatus === "unavailable") {
    return true;
  }

  if (product.isPurchasable === false) {
    return true;
  }

  return false;
}

export function isProductCardPurchaseDisabled(product: {
  isPurchasable?: boolean;
  availabilityStatus?: ProductAvailabilityStatus;
  stock: number;
}): boolean {
  if (product.availabilityStatus === "unavailable" || product.availabilityStatus === "out_of_stock") {
    return true;
  }

  if (product.isPurchasable === false) {
    return true;
  }

  if (product.availabilityStatus === "available") {
    return false;
  }

  return product.stock <= 0;
}

export function resolvePurchaseDisabledLabel(input: {
  disabled: boolean;
  purchaseUnavailable?: boolean;
  availabilityStatus?: ProductAvailabilityStatus;
  configurationId?: string | null;
  variant?: "card" | "detail";
}): string {
  if (!input.disabled) {
    return "Add to Cart";
  }

  if (
    input.purchaseUnavailable
    || input.availabilityStatus === "unavailable"
  ) {
    return "Currently unavailable";
  }

  if (input.variant === "detail" && input.configurationId === null) {
    return "Select options";
  }

  return "Out of Stock";
}

export function resolveProductCardAvailabilityOverlay(product: {
  isPurchasable?: boolean;
  availabilityStatus?: ProductAvailabilityStatus;
  stock: number;
}): string | null {
  if (product.availabilityStatus === "unavailable") {
    return "Currently unavailable";
  }

  if (product.availabilityStatus === "out_of_stock") {
    return "Out of Stock";
  }

  if (isProductPurchaseUnavailable(product)) {
    return "Currently unavailable";
  }

  if (product.stock <= 0) {
    return "Out of Stock";
  }

  return null;
}
