export type ProductBulkActionKey =
  | "publish"
  | "archive"
  | "pricing_percentage_increase"
  | "pricing_percentage_decrease"
  | "pricing_fixed"
  | "inventory_increase"
  | "inventory_decrease"
  | "inventory_set";

export type ProductBulkActionPayload = {
  percent?: number;
  amount?: number;
  quantity?: number;
};

export type ProductBulkActionResultRow = {
  product_id: string;
  success: boolean;
  message: string;
};

export type ProductBulkActionResponse = {
  batch_id: string;
  action_key: ProductBulkActionKey | string;
  total: number;
  succeeded: number;
  failed: number;
  results: ProductBulkActionResultRow[];
};

export type ProductBulkActionDefinition = {
  key: ProductBulkActionKey;
  label: string;
  permission: string;
  needsPercent?: boolean;
  needsAmount?: boolean;
  needsQuantity?: boolean;
  confirmVerb: string;
};

export const PRODUCT_BULK_ACTIONS: ProductBulkActionDefinition[] = [
  {
    key: "publish",
    label: "Publish",
    permission: "catalog.publish",
    confirmVerb: "publish",
  },
  {
    key: "archive",
    label: "Archive",
    permission: "catalog.update",
    confirmVerb: "archive",
  },
  {
    key: "pricing_percentage_increase",
    label: "Price +%",
    permission: "pricing.manage",
    needsPercent: true,
    confirmVerb: "increase prices by percent for",
  },
  {
    key: "pricing_percentage_decrease",
    label: "Price −%",
    permission: "pricing.manage",
    needsPercent: true,
    confirmVerb: "decrease prices by percent for",
  },
  {
    key: "pricing_fixed",
    label: "Set price",
    permission: "pricing.manage",
    needsAmount: true,
    confirmVerb: "set a fixed price for",
  },
  {
    key: "inventory_increase",
    label: "Stock +",
    permission: "inventory.adjust",
    needsQuantity: true,
    confirmVerb: "increase stock for",
  },
  {
    key: "inventory_decrease",
    label: "Stock −",
    permission: "inventory.adjust",
    needsQuantity: true,
    confirmVerb: "decrease stock for",
  },
  {
    key: "inventory_set",
    label: "Set stock",
    permission: "inventory.adjust",
    needsQuantity: true,
    confirmVerb: "set stock for",
  },
];

export function resolveVisibleProductBulkActions(
  permissions: string[] | undefined,
): ProductBulkActionDefinition[] {
  if (permissions === undefined) {
    return PRODUCT_BULK_ACTIONS;
  }

  return PRODUCT_BULK_ACTIONS.filter((action) => {
    if (action.key === "publish") {
      return permissions.includes("catalog.publish") || permissions.includes("catalog.update");
    }
    if (action.key === "archive") {
      return permissions.includes("catalog.archive") || permissions.includes("catalog.update");
    }
    return permissions.includes(action.permission);
  });
}

export function buildProductBulkConfirmationMessage(
  action: ProductBulkActionDefinition,
  selectedCount: number,
  payload: ProductBulkActionPayload,
): string {
  const base = `Apply ${action.confirmVerb} ${selectedCount} selected product${selectedCount === 1 ? "" : "s"}?`;

  if (action.needsPercent) {
    return `${base} Percent: ${payload.percent ?? 0}%.`;
  }
  if (action.needsAmount) {
    return `${base} Amount: ${payload.amount ?? 0}.`;
  }
  if (action.needsQuantity) {
    return `${base} Quantity: ${payload.quantity ?? 0}.`;
  }

  return base;
}

export function validateProductBulkPayload(
  action: ProductBulkActionDefinition,
  payload: ProductBulkActionPayload,
): string | null {
  if (action.needsPercent) {
    const percent = Number(payload.percent);
    if (!Number.isFinite(percent) || percent <= 0) {
      return "Enter a positive percent value.";
    }
  }

  if (action.needsAmount) {
    const amount = Number(payload.amount);
    if (!Number.isFinite(amount) || amount < 0) {
      return "Enter a non-negative amount.";
    }
  }

  if (action.needsQuantity) {
    const quantity = Number(payload.quantity);
    if (!Number.isFinite(quantity) || quantity < 0 || !Number.isInteger(quantity)) {
      return "Enter a whole non-negative quantity.";
    }
    if (
      (action.key === "inventory_increase" || action.key === "inventory_decrease") &&
      quantity <= 0
    ) {
      return "Quantity must be greater than zero.";
    }
  }

  return null;
}

export function summarizeProductBulkResults(result: ProductBulkActionResponse): string {
  return `${result.succeeded} succeeded, ${result.failed} failed of ${result.total}.`;
}

export function groupProductBulkFailures(
  results: ProductBulkActionResultRow[],
): Array<{ message: string; count: number }> {
  const map = new Map<string, number>();
  for (const row of results) {
    if (row.success) continue;
    const message = row.message.trim() || "Unknown failure";
    map.set(message, (map.get(message) ?? 0) + 1);
  }

  return [...map.entries()]
    .map(([message, count]) => ({ message, count }))
    .sort((a, b) => b.count - a.count);
}
