/**
 * PLP / search card availability — server fields only, no local stock math.
 *
 * When no configuration is selected (list/search), use product-level
 * availability_status / is_purchasable. Do not treat missing in_stock on
 * configurable products as out of stock when status says available.
 *
 * Aligns with web product-availability.ts overlay semantics without inventing stock.
 */

export type PlpAvailabilityKind = 'available' | 'out_of_stock' | 'unavailable';

export type PlpAvailability = {
  kind: PlpAvailabilityKind;
  /** Restriction badge; null when the card should not show a stock restriction. */
  badgeLabel: string | null;
};

export type PlpAvailabilityInput = {
  isPurchasable?: boolean | null;
  availabilityStatus?: string | null;
  inStock?: boolean | null;
  commerceChannelCode?: string | null;
};

export function resolvePlpAvailability(
  product: PlpAvailabilityInput,
): PlpAvailability {
  const status = product.availabilityStatus?.trim() || null;

  if (status === 'unavailable' || product.isPurchasable === false) {
    return { kind: 'unavailable', badgeLabel: 'Unavailable' };
  }

  if (status === 'out_of_stock') {
    return { kind: 'out_of_stock', badgeLabel: 'Out of stock' };
  }

  // Explicit available wins over a contradictory/null parent in_stock flag
  // (common on configurable products that only stock variants).
  if (status === 'available') {
    return { kind: 'available', badgeLabel: null };
  }

  // Legacy fallback when status is omitted.
  if (product.inStock === false) {
    // Web China softens stock<=0 when status is missing — do not false-OOS.
    if (product.commerceChannelCode === 'CHINA_IMPORT') {
      return { kind: 'available', badgeLabel: null };
    }
    return { kind: 'out_of_stock', badgeLabel: 'Out of stock' };
  }

  return { kind: 'available', badgeLabel: null };
}

/** Sale presentation from compare-at vs price — no invented discount rules. */
export function isCatalogSalePrice(
  price: string | number | null | undefined,
  compareAtPrice: string | number | null | undefined,
): boolean {
  if (price == null || compareAtPrice == null) return false;
  const current = Number(price);
  const compare = Number(compareAtPrice);
  return Number.isFinite(current) && Number.isFinite(compare) && compare > current;
}
