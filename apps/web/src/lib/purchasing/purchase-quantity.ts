/**
 * Storefront purchase-quantity presentation.
 * Server fields are checkout authority — this module only maps and copies.
 * Do not implement (qty - MOQ) % increment here.
 */

export type PurchaseQuantityPresentation = {
  minimum_quantity: number;
  increment: number | null;
  eligible_quantity: number;
  aggregates_variants: boolean;
  minimum_satisfied: boolean;
  increment_satisfied: boolean;
  quantity_to_minimum: number;
  next_legal_quantity: number;
  construction_complete: boolean;
  blocks_checkout: boolean;
};

export type PurchaseQuantityBlocker = {
  product_id: string;
  minimum_quantity: number;
  increment: number | null;
  eligible_quantity: number;
  minimum_satisfied: boolean;
  increment_satisfied: boolean;
  quantity_to_minimum: number;
  next_legal_quantity: number;
  blocks_checkout: true;
};

export type PurchaseQuantityGuidanceView = {
  minimumLabel: string;
  incrementLabel: string | null;
  allowedExample: string | null;
  status: string | null;
  nextAllowed: string | null;
  mixVariants: string | null;
  incomplete: boolean;
};

export type PurchaseQuantityBlockerView = {
  productId: string;
  status: string;
  nextAllowed: string | null;
  mixVariants: string | null;
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function asInt(value: unknown): number | null {
  if (typeof value === "number" && Number.isInteger(value)) {
    return value;
  }
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || !Number.isInteger(parsed)) {
      return null;
    }
    return parsed;
  }
  return null;
}

function asBool(value: unknown): boolean | null {
  return typeof value === "boolean" ? value : null;
}

function moreCopy(count: number): string {
  return count === 1 ? "1 more" : `${count} more`;
}

/** Cosmetic sequence from the published rule. Not checkout authority. */
export function formatAllowedQuantitiesExample(
  minimum: number,
  increment: number | null,
): string | null {
  if (!Number.isInteger(minimum) || minimum < 1) {
    return null;
  }
  if (increment == null || !Number.isInteger(increment) || increment < 1) {
    return null;
  }

  const sequence = [0, 1, 2, 3].map((step) => minimum + step * increment);
  return `Allowed quantities: ${sequence.join(", ")}, ...`;
}

export function mapPurchaseQuantity(raw: unknown): PurchaseQuantityPresentation | null {
  if (raw == null) {
    return null;
  }
  if (!isRecord(raw)) {
    return null;
  }

  const minimumQuantity = asInt(raw.minimum_quantity);
  const eligibleQuantity = asInt(raw.eligible_quantity);
  const quantityToMinimum = asInt(raw.quantity_to_minimum);
  const nextLegalQuantity = asInt(raw.next_legal_quantity);
  const aggregatesVariants = asBool(raw.aggregates_variants);
  const minimumSatisfied = asBool(raw.minimum_satisfied);
  const incrementSatisfied = asBool(raw.increment_satisfied);
  const constructionComplete = asBool(raw.construction_complete);
  const blocksCheckout = asBool(raw.blocks_checkout);

  if (
    minimumQuantity == null ||
    minimumQuantity < 1 ||
    eligibleQuantity == null ||
    eligibleQuantity < 0 ||
    quantityToMinimum == null ||
    quantityToMinimum < 0 ||
    nextLegalQuantity == null ||
    nextLegalQuantity < 1 ||
    aggregatesVariants == null ||
    minimumSatisfied == null ||
    incrementSatisfied == null ||
    constructionComplete == null ||
    blocksCheckout == null
  ) {
    return null;
  }

  const incrementRaw = raw.increment;
  const increment =
    incrementRaw == null ? null : asInt(incrementRaw);
  if (incrementRaw != null && (increment == null || increment < 1)) {
    return null;
  }

  return {
    minimum_quantity: minimumQuantity,
    increment,
    eligible_quantity: eligibleQuantity,
    aggregates_variants: aggregatesVariants,
    minimum_satisfied: minimumSatisfied,
    increment_satisfied: incrementSatisfied,
    quantity_to_minimum: quantityToMinimum,
    next_legal_quantity: nextLegalQuantity,
    construction_complete: constructionComplete,
    blocks_checkout: blocksCheckout,
  };
}

export function mapPurchaseQuantityBlocker(raw: unknown): PurchaseQuantityBlocker | null {
  if (!isRecord(raw)) {
    return null;
  }

  const productId = typeof raw.product_id === "string" ? raw.product_id.trim() : "";
  const minimumQuantity = asInt(raw.minimum_quantity);
  const eligibleQuantity = asInt(raw.eligible_quantity);
  const quantityToMinimum = asInt(raw.quantity_to_minimum);
  const nextLegalQuantity = asInt(raw.next_legal_quantity);
  const minimumSatisfied = asBool(raw.minimum_satisfied);
  const incrementSatisfied = asBool(raw.increment_satisfied);

  if (
    !productId ||
    minimumQuantity == null ||
    minimumQuantity < 1 ||
    eligibleQuantity == null ||
    eligibleQuantity < 0 ||
    quantityToMinimum == null ||
    quantityToMinimum < 0 ||
    nextLegalQuantity == null ||
    nextLegalQuantity < 1 ||
    minimumSatisfied == null ||
    incrementSatisfied == null
  ) {
    return null;
  }

  const incrementRaw = raw.increment;
  const increment = incrementRaw == null ? null : asInt(incrementRaw);
  if (incrementRaw != null && (increment == null || increment < 1)) {
    return null;
  }

  return {
    product_id: productId,
    minimum_quantity: minimumQuantity,
    increment,
    eligible_quantity: eligibleQuantity,
    minimum_satisfied: minimumSatisfied,
    increment_satisfied: incrementSatisfied,
    quantity_to_minimum: quantityToMinimum,
    next_legal_quantity: nextLegalQuantity,
    blocks_checkout: true,
  };
}

export function mapPurchaseQuantityBlockers(raw: unknown): PurchaseQuantityBlocker[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  const blockers: PurchaseQuantityBlocker[] = [];
  const seen = new Set<string>();

  for (const entry of raw) {
    const blocker = mapPurchaseQuantityBlocker(entry);
    if (!blocker || seen.has(blocker.product_id)) {
      continue;
    }
    seen.add(blocker.product_id);
    blockers.push(blocker);
  }

  return blockers;
}

export function resolveQuotePurchaseQuantity(
  quote: { quantity: number; purchase_quantity?: PurchaseQuantityPresentation | null } | null,
  requestedQuantity: number,
): PurchaseQuantityPresentation | null {
  if (!quote || quote.quantity !== requestedQuantity) {
    return null;
  }
  return quote.purchase_quantity ?? null;
}

export function isAddToCartBlockedByPurchaseQuantity(): boolean {
  return false;
}

export function shouldInterceptBuyNow(
  quote: { quantity: number; purchase_quantity?: PurchaseQuantityPresentation | null } | null,
  requestedQuantity: number,
): boolean {
  const presentation = resolveQuotePurchaseQuantity(quote, requestedQuantity);
  return presentation?.blocks_checkout === true;
}

export function shouldBlockCheckoutCta(blockers: readonly PurchaseQuantityBlocker[] | null | undefined): boolean {
  return Array.isArray(blockers) && blockers.length > 0;
}

export function selectBlockerForProduct(
  blockers: readonly PurchaseQuantityBlocker[] | null | undefined,
  productId: string | null | undefined,
): PurchaseQuantityBlocker | null {
  const id = productId?.trim();
  if (!id || !Array.isArray(blockers)) {
    return null;
  }
  return blockers.find((blocker) => blocker.product_id === id) ?? null;
}

export function resolvePdpPurchaseQuantityView(
  presentation: PurchaseQuantityPresentation | null,
): PurchaseQuantityGuidanceView | null {
  if (!presentation) {
    return null;
  }

  const incomplete = presentation.blocks_checkout === true;
  let status: string | null = null;
  let nextAllowed: string | null = null;

  if (!presentation.minimum_satisfied && presentation.quantity_to_minimum > 0) {
    status = `Add ${moreCopy(presentation.quantity_to_minimum)} to reach the minimum.`;
  } else if (presentation.minimum_satisfied && presentation.increment_satisfied) {
    status = "Minimum reached.";
  }

  if (
    presentation.increment != null &&
    !presentation.increment_satisfied &&
    presentation.next_legal_quantity !== presentation.eligible_quantity
  ) {
    nextAllowed = `Next allowed quantity: ${presentation.next_legal_quantity}`;
  }

  return {
    minimumLabel: `Minimum order quantity: ${presentation.minimum_quantity}`,
    incrementLabel:
      presentation.increment != null ? `Order increment: ${presentation.increment}` : null,
    allowedExample: formatAllowedQuantitiesExample(
      presentation.minimum_quantity,
      presentation.increment,
    ),
    status,
    nextAllowed,
    mixVariants: presentation.aggregates_variants
      ? "You can mix variants to reach the required quantity."
      : null,
    incomplete,
  };
}

export function resolveCartBlockerView(
  blocker: PurchaseQuantityBlocker,
  aggregatesVariants = false,
): PurchaseQuantityBlockerView {
  let status: string;
  let nextAllowed: string | null = null;

  if (!blocker.minimum_satisfied && blocker.quantity_to_minimum > 0) {
    status = `Add ${moreCopy(blocker.quantity_to_minimum)} of this product to reach the minimum order quantity.`;
  } else if (!blocker.increment_satisfied) {
    status = `Quantity ${blocker.eligible_quantity} is not an allowed total.`;
    if (blocker.next_legal_quantity !== blocker.eligible_quantity) {
      nextAllowed = `Next allowed quantity: ${blocker.next_legal_quantity}.`;
    }
  } else {
    status = "This product does not meet the purchase quantity rule.";
  }

  return {
    productId: blocker.product_id,
    status,
    nextAllowed,
    mixVariants: aggregatesVariants ? "Any variant counts toward this total." : null,
  };
}

export function formatPurchaseQuantityCheckoutMessage(
  blocker: PurchaseQuantityBlocker | null | undefined,
): string | null {
  if (!blocker) {
    return null;
  }

  if (!blocker.minimum_satisfied && blocker.quantity_to_minimum > 0) {
    return `Add ${moreCopy(blocker.quantity_to_minimum)} before checkout.`;
  }

  if (!blocker.increment_satisfied && blocker.next_legal_quantity !== blocker.eligible_quantity) {
    return `Next allowed quantity is ${blocker.next_legal_quantity}.`;
  }

  return "This product does not meet the purchase quantity rule.";
}

export function presentationAsBlocker(
  presentation: PurchaseQuantityPresentation,
  productId = "",
): PurchaseQuantityBlocker {
  return {
    product_id: productId,
    minimum_quantity: presentation.minimum_quantity,
    increment: presentation.increment,
    eligible_quantity: presentation.eligible_quantity,
    minimum_satisfied: presentation.minimum_satisfied,
    increment_satisfied: presentation.increment_satisfied,
    quantity_to_minimum: presentation.quantity_to_minimum,
    next_legal_quantity: presentation.next_legal_quantity,
    blocks_checkout: true,
  };
}

export function formatBuyNowInterceptMessage(
  presentation: PurchaseQuantityPresentation | null | undefined,
): string | null {
  if (!presentation?.blocks_checkout) {
    return null;
  }
  return formatPurchaseQuantityCheckoutMessage(presentationAsBlocker(presentation));
}

export function formatAddToCartFollowUp(
  blocker: PurchaseQuantityBlocker | null | undefined,
): string | null {
  if (!blocker) {
    return null;
  }

  if (!blocker.minimum_satisfied && blocker.quantity_to_minimum > 0) {
    return `Added. Add ${moreCopy(blocker.quantity_to_minimum)} before checkout.`;
  }

  if (!blocker.increment_satisfied && blocker.next_legal_quantity !== blocker.eligible_quantity) {
    return `Added. Next allowed quantity: ${blocker.next_legal_quantity}.`;
  }

  return null;
}

export function parsePurchaseQuantityCheckoutError(payload: unknown): {
  code: string | null;
  blocker: PurchaseQuantityBlocker | null;
} {
  if (!isRecord(payload)) {
    return { code: null, blocker: null };
  }

  const code = typeof payload.code === "string" ? payload.code : null;
  const data = isRecord(payload.data) ? payload.data : null;
  const blocker = mapPurchaseQuantityBlocker(data?.purchase_quantity);

  return {
    code,
    blocker: code === "purchase_quantity_unsatisfied" || blocker ? blocker : null,
  };
}
