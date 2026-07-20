import {
  fetchStorefrontQuote,
  type StorefrontPriceQuote,
} from "@/lib/catalog/storefront-configuration";

export type CartMoqHint = {
  remainingQuantity: number;
  targetQuantity: number;
  nextUnitPrice: number;
  currentUnitPrice: number;
  /** Per-unit drop at the target MOQ tier vs baseline. */
  savingsPerUnit: number;
  /** Total you would save buying targetQuantity at the MOQ unit price vs baseline. */
  totalSavings: number;
  /** Baseline (pre–quantity-tier) unit price from the quote engine. */
  baselineUnitPrice: number;
};

export type CartMoqQuoteResult = {
  unitPrice: number;
  compareAtUnitPrice: number;
  quantityTierApplied: boolean;
  moqDiscountPerUnit: number;
  appliedTierMinQuantity: number | null;
  quote: StorefrontPriceQuote;
};

export type DiscoveredMoqPlan = {
  /** Highest discounting tier min_quantity from the quote engine. */
  targetQuantity: number;
  targetUnitPrice: number;
  baselineUnitPrice: number;
  remainingQuantity: number;
  savingsPerUnit: number;
  totalSavings: number;
};

function readMetaNumber(meta: Record<string, unknown> | undefined, key: string): number | null {
  const value = meta?.[key];
  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

/**
 * Unit price immediately before the quantity_tier stage — the true compare-at for MOQ.
 */
export function extractCompareAtUnitPrice(quote: StorefrontPriceQuote): number {
  const tierIndex = quote.breakdown.findIndex((stage) => stage.stage === "quantity_tier");
  const beforeTier =
    tierIndex >= 0 ? quote.breakdown.slice(0, tierIndex) : quote.breakdown;

  const preTier =
    [...beforeTier].reverse().find((stage) => stage.applied) ??
    beforeTier.find(
      (stage) =>
        stage.stage === "configuration_override" || stage.stage === "base",
    );

  const compareAt = Number.parseFloat(preTier?.unit_price ?? quote.unit_price);
  return Number.isFinite(compareAt) ? compareAt : Number.parseFloat(quote.unit_price);
}

export function extractAppliedQuantityTier(quote: StorefrontPriceQuote): {
  minQuantity: number;
  unitPrice: number;
} | null {
  const stage = quote.breakdown.find(
    (entry) => entry.stage === "quantity_tier" && entry.applied,
  );
  if (!stage) return null;

  const minQuantity = readMetaNumber(stage.meta, "min_quantity");
  const unitPrice = Number.parseFloat(stage.unit_price);
  if (minQuantity == null || minQuantity < 1 || !Number.isFinite(unitPrice)) {
    return null;
  }

  return { minQuantity, unitPrice };
}

export function isQuantityTierApplied(quote: StorefrontPriceQuote): boolean {
  return quote.breakdown.some((stage) => stage.stage === "quantity_tier" && stage.applied);
}

export function parseQuoteUnitPrice(quote: StorefrontPriceQuote): number {
  const price = Number.parseFloat(quote.unit_price);
  return Number.isFinite(price) ? price : 0;
}

function uniqueSorted(values: number[]): number[] {
  return [...new Set(values)]
    .filter((value) => Number.isFinite(value) && value > 0)
    .sort((a, b) => a - b);
}

/**
 * Discover the primary MOQ (highest discounting min_quantity) from quote engine tiers.
 * Uses quantity_tier.meta.min_quantity from probed quotes — no hardcoded thresholds.
 */
export async function discoverMoqPlan(input: {
  slug: string;
  configurationId: string;
  currentQuantity: number;
  stock: number;
}): Promise<DiscoveredMoqPlan | null> {
  const { slug, configurationId, currentQuantity, stock } = input;
  const maxQty = Math.min(Math.max(1, stock), 99);

  // Probe enough quantities to surface every tier breakpoint the engine can apply.
  const probeQuantities = uniqueSorted([
    1,
    currentQuantity,
    ...Array.from({ length: Math.min(maxQty, 20) }, (_, index) => index + 1),
    25,
    30,
    40,
    50,
    maxQty,
  ]).filter((qty) => qty <= maxQty);

  const quotes = await Promise.all(
    probeQuantities.map(async (quantity) => {
      try {
        return await fetchStorefrontQuote({
          slug,
          configurationId,
          quantity,
        });
      } catch {
        return null;
      }
    }),
  );

  const validQuotes = quotes.filter((quote): quote is StorefrontPriceQuote => Boolean(quote));
  if (validQuotes.length === 0) return null;

  const baselineQuote =
    validQuotes.find((quote) => quote.quantity === 1) ?? validQuotes[0];
  const baselineUnitPrice = extractCompareAtUnitPrice(baselineQuote);

  /** Map of min_quantity → unit_price from applied quantity_tier stages. */
  const tierMap = new Map<number, number>();

  for (const quote of validQuotes) {
    const applied = extractAppliedQuantityTier(quote);
    if (!applied) continue;
    // Prefer the unit price observed at the tier's own min_quantity probe when available.
    const existing = tierMap.get(applied.minQuantity);
    if (existing == null || quote.quantity === applied.minQuantity) {
      tierMap.set(applied.minQuantity, applied.unitPrice);
    }
  }

  if (tierMap.size === 0) return null;

  // Discounting tiers = unit price meaningfully below the pre-tier baseline.
  const discountingTiers = [...tierMap.entries()]
    .filter(([, unitPrice]) => unitPrice < baselineUnitPrice - 0.001)
    .sort((a, b) => a[0] - b[0]);

  if (discountingTiers.length === 0) return null;

  // Primary MOQ = highest min_quantity among discounting tiers (true wholesale threshold).
  const [targetQuantity, targetUnitPrice] = discountingTiers[discountingTiers.length - 1];
  const remainingQuantity = Math.max(0, targetQuantity - currentQuantity);
  const savingsPerUnit = Math.max(0, baselineUnitPrice - targetUnitPrice);

  return {
    targetQuantity,
    targetUnitPrice,
    baselineUnitPrice,
    remainingQuantity,
    savingsPerUnit,
    totalSavings: savingsPerUnit * targetQuantity,
  };
}

/** @deprecated Prefer discoverMoqPlan — kept for cart callers. */
export async function discoverNextMoqTier(input: {
  slug: string;
  configurationId: string;
  currentQuantity: number;
  currentUnitPrice: number;
  stock: number;
}): Promise<CartMoqHint | null> {
  const plan = await discoverMoqPlan({
    slug: input.slug,
    configurationId: input.configurationId,
    currentQuantity: input.currentQuantity,
    stock: input.stock,
  });

  if (!plan || plan.remainingQuantity <= 0) return null;

  return {
    remainingQuantity: plan.remainingQuantity,
    targetQuantity: plan.targetQuantity,
    nextUnitPrice: plan.targetUnitPrice,
    currentUnitPrice: input.currentUnitPrice,
    savingsPerUnit: plan.savingsPerUnit,
    totalSavings: plan.totalSavings,
    baselineUnitPrice: plan.baselineUnitPrice,
  };
}

export async function quoteCartLine(input: {
  slug: string;
  configurationId: string;
  quantity: number;
}): Promise<CartMoqQuoteResult> {
  const quote = await fetchStorefrontQuote(input);
  const unitPrice = parseQuoteUnitPrice(quote);
  const compareAtUnitPrice = extractCompareAtUnitPrice(quote);
  const appliedTier = extractAppliedQuantityTier(quote);
  const quantityTierApplied =
    Boolean(appliedTier) && compareAtUnitPrice > unitPrice + 0.001;

  return {
    quote,
    unitPrice,
    compareAtUnitPrice: quantityTierApplied ? compareAtUnitPrice : unitPrice,
    quantityTierApplied,
    moqDiscountPerUnit: quantityTierApplied
      ? Math.max(0, compareAtUnitPrice - unitPrice)
      : 0,
    appliedTierMinQuantity: appliedTier?.minQuantity ?? null,
  };
}
