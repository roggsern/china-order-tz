import type {
  CatalogProductDetail,
  ProductConfiguration,
} from '../models/types';

/**
 * Customer-facing PDP availability kind from server flags only.
 * No local stock math — mirrors API is_purchasable / is_in_stock / availability_status.
 *
 * Configurable + concrete match:
 *   use match-level configuration.isInStock / isPurchasable only.
 *   Do NOT prefer aggregate availability_status or product.inStock once a sell unit is matched.
 *
 * Simple (hasConfigurations === false) or config not yet loaded:
 *   use product-level purchasability / availability_status / inStock.
 */
export type PdpAvailabilityKind =
  | 'select_options'
  | 'available'
  | 'out_of_stock'
  | 'unavailable';

export function resolvePdpAvailabilityKind(params: {
  product: CatalogProductDetail;
  configuration?: ProductConfiguration | null;
}): PdpAvailabilityKind {
  const { product, configuration } = params;

  if (configuration?.hasConfigurations) {
    if (!configuration.isComplete || !configuration.matchedConfigurationId) {
      return 'select_options';
    }

    // Matched sell unit — server match flags only (ShowProductConfigurationAction).
    if (configuration.isPurchasable === false) {
      return 'unavailable';
    }
    if (configuration.isInStock === false) {
      return 'out_of_stock';
    }
    return 'available';
  }

  if (product.isPurchasable === false) {
    return 'unavailable';
  }
  if (product.availabilityStatus === 'unavailable') {
    return 'unavailable';
  }
  if (product.availabilityStatus === 'out_of_stock') {
    return 'out_of_stock';
  }
  if (product.inStock === false) {
    return 'out_of_stock';
  }

  return 'available';
}

export function customerLabelForAvailabilityKind(
  kind: PdpAvailabilityKind,
): string {
  switch (kind) {
    case 'select_options':
      return 'Select options';
    case 'available':
      return 'Available';
    case 'out_of_stock':
      return 'Out of stock';
    case 'unavailable':
      return 'Unavailable';
  }
}

/** True when the customer may attempt ATC for this resolved kind (ignores loading/qty). */
export function isPurchasableAvailabilityKind(kind: PdpAvailabilityKind): boolean {
  return kind === 'available';
}
