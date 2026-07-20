"use client";

import { useEffect, useMemo, useState } from "react";
import {
  discoverMoqPlan,
  extractAppliedQuantityTier,
  extractCompareAtUnitPrice,
  type CartMoqHint,
  type DiscoveredMoqPlan,
} from "@/lib/cart/quote";
import type { StorefrontPriceQuote } from "@/lib/catalog/storefront-configuration";

/**
 * Resolve MOQ guidance + unlock state from existing storefront quotes.
 * Does not change pricing — display helpers only.
 */
export function useMoqDisplayState(input: {
  quote: StorefrontPriceQuote | null;
  slug: string;
  configurationId: string | null;
  quantity: number;
  stock: number;
  enabled: boolean;
}) {
  const { quote, slug, configurationId, quantity, stock, enabled } = input;
  const [plan, setPlan] = useState<DiscoveredMoqPlan | null>(null);

  const unitPrice = quote ? Number.parseFloat(quote.unit_price) : null;
  const compareAtUnitPrice = quote ? extractCompareAtUnitPrice(quote) : null;
  const appliedTier = quote ? extractAppliedQuantityTier(quote) : null;

  // Re-discover when configuration / stock changes (not on every qty — plan target is stable).
  useEffect(() => {
    if (!enabled || !configurationId) {
      setPlan(null);
      return;
    }

    let cancelled = false;

    void discoverMoqPlan({
      slug,
      configurationId,
      currentQuantity: quantity,
      stock,
    }).then((nextPlan) => {
      if (!cancelled) setPlan(nextPlan);
    });

    return () => {
      cancelled = true;
    };
    // Intentionally exclude quantity from discovery triggers for the target tier itself;
    // remainingQuantity is derived below from the latest quantity + plan.targetQuantity.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, configurationId, slug, stock]);

  // Keep remainingQuantity in sync when quantity changes without re-probing all tiers.
  const livePlan = useMemo(() => {
    if (!plan) return null;
    return {
      ...plan,
      remainingQuantity: Math.max(0, plan.targetQuantity - quantity),
    };
  }, [plan, quantity]);

  const savingsAgainstBaseline =
    unitPrice != null && compareAtUnitPrice != null
      ? Math.max(0, (compareAtUnitPrice - unitPrice) * quantity)
      : 0;

  /**
   * Unlocked only once the primary MOQ threshold from the quote engine is reached.
   * Intermediate cheaper tiers (e.g. qty 3 when primary is 5) stay in "add more" mode.
   */
  const wholesaleUnlocked = Boolean(
    enabled &&
      livePlan &&
      unitPrice != null &&
      compareAtUnitPrice != null &&
      quantity >= livePlan.targetQuantity &&
      compareAtUnitPrice > unitPrice + 0.001 &&
      (unitPrice <= livePlan.targetUnitPrice + 0.5 ||
        (appliedTier?.minQuantity ?? 0) >= livePlan.targetQuantity),
  );

  const moqHint: CartMoqHint | null =
    !wholesaleUnlocked && livePlan && livePlan.remainingQuantity > 0
      ? {
          remainingQuantity: livePlan.remainingQuantity,
          targetQuantity: livePlan.targetQuantity,
          nextUnitPrice: livePlan.targetUnitPrice,
          currentUnitPrice: unitPrice ?? livePlan.baselineUnitPrice,
          savingsPerUnit: livePlan.savingsPerUnit,
          totalSavings: livePlan.totalSavings,
          baselineUnitPrice: livePlan.baselineUnitPrice,
        }
      : null;

  return {
    unitPrice,
    compareAtUnitPrice,
    wholesaleApplied: wholesaleUnlocked,
    moqHint,
    moqDiscount: wholesaleUnlocked ? savingsAgainstBaseline : 0,
    moqPlan: livePlan,
  };
}
