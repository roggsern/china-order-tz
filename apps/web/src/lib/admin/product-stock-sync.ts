import type { AdminProductStock } from "@/lib/api/admin-catalog";

export type ProductStockFormState = {
  quantity: number;
};

export function emptyProductStockFormState(): ProductStockFormState {
  return { quantity: 0 };
}

export function mapProductStockToFormState(stock: AdminProductStock): ProductStockFormState {
  return {
    quantity: Math.max(0, Math.floor(stock.quantity)),
  };
}

export function buildProductStockUpdatePayload(form: ProductStockFormState): {
  stock_quantity: number;
} {
  return {
    stock_quantity: Math.max(0, Math.floor(form.quantity)),
  };
}

export function validateProductStockFormState(
  form: ProductStockFormState,
): Record<string, string> {
  const errors: Record<string, string> = {};

  if (!Number.isFinite(form.quantity) || form.quantity < 0) {
    errors.quantity = "Stock quantity must be zero or greater.";
  }

  if (!Number.isInteger(form.quantity)) {
    errors.quantity = "Stock quantity must be a whole number.";
  }

  return errors;
}
