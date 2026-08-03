export type ProductCommercialStockFormState = {
  availableQuantity: number;
};

export type AdminCommercialVariantStock = {
  variantId: string;
  name: string;
  sku: string;
  isActive: boolean;
  availableQuantity: number;
  reservedQuantity: number;
  orderedQuantity: number;
  commercialStockId: string | null;
};

export type AdminProductCommercialStock = {
  path: "simple" | "variant";
  simple: {
    commercialStockId: string | null;
    availableQuantity: number;
    reservedQuantity: number;
    orderedQuantity: number;
  } | null;
  variants: AdminCommercialVariantStock[];
};

export function emptyProductCommercialStockFormState(): ProductCommercialStockFormState {
  return { availableQuantity: 0 };
}

export function validateCommercialAvailableQuantity(quantity: number): string | null {
  if (!Number.isFinite(quantity) || quantity < 0) {
    return "Available quantity must be zero or greater.";
  }

  if (!Number.isInteger(quantity)) {
    return "Available quantity must be a whole number.";
  }

  return null;
}

export function buildProductCommercialStockUpdatePayload(
  form: ProductCommercialStockFormState,
): { available_quantity: number } {
  return {
    available_quantity: Math.max(0, Math.floor(form.availableQuantity)),
  };
}

export function buildVariantCommercialStockUpdatePayload(availableQuantity: number): {
  available_quantity: number;
} {
  return {
    available_quantity: Math.max(0, Math.floor(availableQuantity)),
  };
}
