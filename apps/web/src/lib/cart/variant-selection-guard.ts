import type { Product } from "@/lib/types/catalog";

export const VARIANT_SELECTION_REQUIRED_MESSAGE =
  "Select product options before adding to cart.";

/**
 * Defensive guest/local-cart invariant: known Variant-path products must not
 * succeed with a null configurationId. Undefined means the contract field is
 * absent — do not guess.
 */
export function rejectNullConfigurationForVariantPathProduct(
  product: Pick<Product, "requiresVariantSelection">,
  configurationId?: string | null,
): string | null {
  if (product.requiresVariantSelection !== true) {
    return null;
  }

  if (configurationId?.trim()) {
    return null;
  }

  return VARIANT_SELECTION_REQUIRED_MESSAGE;
}
