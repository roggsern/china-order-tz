export type VariantBulkActionKey =
  | "set_selling_price"
  | "set_cost_price"
  | "set_commercial_stock"
  | "set_inventory_stock"
  | "activate"
  | "deactivate";

export type VariantBulkActionPayload = {
  amount?: number;
  cost_price?: number;
  available_quantity?: number;
  on_hand?: number;
  quantity?: number;
  warehouse_code?: string;
  reserved?: number;
  reorder_level?: number;
  safety_stock?: number;
  is_active?: boolean;
};

export type VariantBulkActionResultRow = {
  variant_id: string;
  success: boolean;
  message: string;
};

export type VariantBulkActionResponse = {
  batch_id: string;
  action_key: VariantBulkActionKey | string;
  product_id: string;
  total: number;
  succeeded: number;
  failed: number;
  results: VariantBulkActionResultRow[];
};

export type VariantBulkFieldValues = {
  sellingPrice: string;
  costPrice: string;
  stockQuantity: string;
};

export function summarizeVariantBulkResults(responses: VariantBulkActionResponse[]): {
  succeeded: number;
  failed: number;
  failures: VariantBulkActionResultRow[];
} {
  const failures = responses.flatMap((response) =>
    response.results.filter((row) => !row.success),
  );
  const succeeded = responses.reduce((sum, response) => sum + response.succeeded, 0);
  const failed = responses.reduce((sum, response) => sum + response.failed, 0);

  return { succeeded, failed, failures };
}

export function parseBulkNumericField(value: string): number | null {
  const trimmed = value.trim();
  if (trimmed === "") {
    return null;
  }

  const parsed = Number(trimmed);
  if (!Number.isFinite(parsed) || parsed < 0) {
    return null;
  }

  return parsed;
}

export function validateVariantBulkFields(
  values: VariantBulkFieldValues,
  isChinaImport: boolean,
): string | null {
  const hasSelling = values.sellingPrice.trim() !== "";
  const hasCost = values.costPrice.trim() !== "";
  const hasStock = values.stockQuantity.trim() !== "";

  if (!hasSelling && !hasCost && !hasStock) {
    return "Enter at least one value to apply.";
  }

  if (hasSelling && parseBulkNumericField(values.sellingPrice) === null) {
    return "Selling price must be zero or greater.";
  }

  if (hasCost && parseBulkNumericField(values.costPrice) === null) {
    return "Cost price must be zero or greater.";
  }

  if (hasStock && parseBulkNumericField(values.stockQuantity) === null) {
    return isChinaImport
      ? "Commercial stock must be zero or greater."
      : "Warehouse stock must be zero or greater.";
  }

  return null;
}

export function stockFieldLabel(isChinaImport: boolean): string {
  return isChinaImport ? "Commercial stock" : "Warehouse stock";
}
