import type {
  CatalogProductDetail,
  ProductConfiguration,
} from '../models/types';
import {
  isPurchasableAvailabilityKind,
  resolvePdpAvailabilityKind,
  type PdpAvailabilityKind,
} from './resolvePdpAvailability';

export type AddToCartButtonLabel =
  | 'Checking availability...'
  | 'Select options'
  | 'Unavailable'
  | 'Add to cart';

export type AddToCartGate = {
  canAdd: boolean;
  label: AddToCartButtonLabel;
};

function gateLabelForKind(kind: PdpAvailabilityKind): AddToCartButtonLabel {
  switch (kind) {
    case 'select_options':
      return 'Select options';
    case 'available':
      return 'Add to cart';
    case 'out_of_stock':
    case 'unavailable':
      return 'Unavailable';
  }
}

/**
 * Client gate for Add to Cart — uses the same sell-unit availability as the PDP badge.
 * Does not compute variants or stock locally.
 * Disables while configuration is loading or failed (race-safe).
 * Backend ResolveCartPurchasable remains the stock authority at submit.
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

  const kind = resolvePdpAvailabilityKind({
    product: params.product,
    configuration: config,
  });

  return {
    canAdd: isPurchasableAvailabilityKind(kind),
    label: gateLabelForKind(kind),
  };
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
