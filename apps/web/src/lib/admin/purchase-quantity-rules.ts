export function parseNullableInt(value: unknown): number | null {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  if (typeof value === "number" && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === "string") {
    const trimmed = value.trim();
    if (trimmed === "") {
      return null;
    }
    const parsed = Number(trimmed);
    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

export function parsePurchaseQuantityInput(raw: string): number | null {
  const trimmed = raw.trim();
  if (trimmed === "") {
    return null;
  }

  return Number(trimmed);
}

function toWritableQuantity(value: number | null): number | null {
  if (value === null) {
    return null;
  }

  // JSON.stringify(NaN) becomes null and would silently clear a stored rule.
  if (!Number.isFinite(value)) {
    throw new Error("Purchase quantity payload values must be finite numbers or null.");
  }

  return value;
}

export function purchaseQuantityWriteFields(
  minimumOrderQuantity: number | null,
  orderIncrement: number | null,
): {
  minimum_order_quantity: number | null;
  order_increment: number | null;
} {
  return {
    minimum_order_quantity: toWritableQuantity(minimumOrderQuantity),
    order_increment: toWritableQuantity(orderIncrement),
  };
}

export function formatPurchaseQuantityAllowedExample(
  minimum: number | null,
  increment: number | null,
): string | null {
  if (minimum === null || !Number.isInteger(minimum) || minimum < 1) {
    return null;
  }

  if (increment === null) {
    return `Allowed: ${minimum}, ${minimum + 1}, ${minimum + 2}, ${minimum + 3}, ...`;
  }

  if (!Number.isInteger(increment) || increment < 1) {
    return null;
  }

  const sequence = [0, 1, 2, 3].map((step) => minimum + step * increment);
  return `Allowed: ${sequence.join(", ")}, ...`;
}

export function purchaseQuantityFormErrors(
  minimum: number | null,
  increment: number | null,
): { minimumOrderQuantity?: string; orderIncrement?: string } {
  const errors: { minimumOrderQuantity?: string; orderIncrement?: string } = {};

  if (minimum !== null && (!Number.isInteger(minimum) || minimum < 1)) {
    errors.minimumOrderQuantity = "Minimum order quantity must be a whole number of at least 1.";
  }

  if (increment !== null && (!Number.isInteger(increment) || increment < 1)) {
    errors.orderIncrement = "Order increment must be a whole number of at least 1.";
  } else if (increment !== null && minimum === null) {
    errors.orderIncrement = "An order increment requires a minimum order quantity.";
  }

  return errors;
}
