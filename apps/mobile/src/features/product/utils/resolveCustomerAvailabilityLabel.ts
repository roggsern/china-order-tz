import type { CatalogProductDetail, ProductConfiguration } from '../models/types';
import {
  customerLabelForAvailabilityKind,
  resolvePdpAvailabilityKind,
} from './resolvePdpAvailability';

/**
 * Single customer-facing availability label from server fields only.
 * Configurable matches use sell-unit flags — not aggregate product status.
 */
export function resolveCustomerAvailabilityLabel(params: {
  product: CatalogProductDetail;
  configuration?: ProductConfiguration | null;
}): string {
  return customerLabelForAvailabilityKind(resolvePdpAvailabilityKind(params));
}
