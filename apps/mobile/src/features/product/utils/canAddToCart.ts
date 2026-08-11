import type {
  CatalogProductDetail,
  ProductConfiguration,
} from '../models/types';

export type AddToCartButtonLabel =
  | 'Checking availability...'
  | 'Select options'
  | 'Unavailable'
  | 'Add to cart';

export type AddToCartGate = {
  canAdd: boolean;
  label: AddToCartButtonLabel;
};

/**
 * Client gate for Add to Cart — uses API purchasability / match flags only.
 * Does not compute variants or stock locally.
 * Disables while configuration is loading or failed (race-safe).
 */
export function resolveAddToCartGate(params: {
  product: CatalogProductDetail;
  configuration?: ProductConfiguration | null;
  configurationLoading?: boolean;
  configurationError?: boolean;
  quantity: number;
  submitting?: boolean;
}): AddToCartGate {
  if (params.submitting) {
    return { canAdd: false, label: 'Add to cart' };
  }
  if (!Number.isFinite(params.quantity) || params.quantity < 1) {
    return { canAdd: false, label: 'Unavailable' };
  }

  if (params.configurationLoading) {
    return { canAdd: false, label: 'Checking availability...' };
  }

  if (params.configurationError) {
    return { canAdd: false, label: 'Unavailable' };
  }

  const config = params.configuration;
  // Configuration must resolve from the server before ATC — never assume simple.
  if (config == null) {
    return { canAdd: false, label: 'Checking availability...' };
  }

  if (config.hasConfigurations) {
    if (!config.isComplete || !config.matchedConfigurationId) {
      return { canAdd: false, label: 'Select options' };
    }
    if (config.isPurchasable === false || config.isInStock === false) {
      return { canAdd: false, label: 'Unavailable' };
    }
    return { canAdd: true, label: 'Add to cart' };
  }

  if (params.product.isPurchasable === false) {
    return { canAdd: false, label: 'Unavailable' };
  }
  if (params.product.availabilityStatus === 'unavailable') {
    return { canAdd: false, label: 'Unavailable' };
  }
  if (params.product.availabilityStatus === 'out_of_stock') {
    return { canAdd: false, label: 'Unavailable' };
  }
  if (params.product.inStock === false) {
    return { canAdd: false, label: 'Unavailable' };
  }

  return { canAdd: true, label: 'Add to cart' };
}

/** @deprecated Prefer resolveAddToCartGate — kept for call-site migration. */
export function canAddToCart(params: {
  product: CatalogProductDetail;
  configuration?: ProductConfiguration | null;
  configurationLoading?: boolean;
  configurationError?: boolean;
  quantity: number;
  submitting?: boolean;
}): boolean {
  return resolveAddToCartGate(params).canAdd;
}
