import type { CatalogProductDetail, ProductConfiguration } from '../models/types';

/**
 * Single customer-facing availability label from server fields only.
 * Avoids exposing conflicting technical flags (purchasable vs config stock).
 */
export function resolveCustomerAvailabilityLabel(params: {
  product: CatalogProductDetail;
  configuration?: ProductConfiguration | null;
}): string {
  const { product, configuration } = params;

  if (configuration?.hasConfigurations && !configuration.isComplete) {
    return 'Select options';
  }

  if (
    configuration?.isPurchasable === false ||
    configuration?.isInStock === false ||
    product.isPurchasable === false ||
    product.inStock === false
  ) {
    const status =
      configuration?.availabilityStatus ?? product.availabilityStatus;
    if (status === 'unavailable') return 'Unavailable';
    return 'Out of stock';
  }

  const status =
    configuration?.availabilityStatus ?? product.availabilityStatus;
  if (status === 'out_of_stock') return 'Out of stock';
  if (status === 'unavailable') return 'Unavailable';
  if (status === 'available') return 'Available';

  if (product.inStock === true || configuration?.isPurchasable === true) {
    return 'Available';
  }

  if (configuration?.hasConfigurations) {
    return 'Select options';
  }

  return 'Available';
}
