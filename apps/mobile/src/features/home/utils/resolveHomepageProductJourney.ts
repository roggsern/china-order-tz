import { isCommerceJourney, type CommerceJourney } from '@/src/shared/types/commerce';
import type { HomepageProductCard } from '../models/types';

/**
 * Homepage PDP navigation must use product channel identity from the server.
 * Never coerce into the active UI journey.
 */
export function resolveHomepageProductJourney(
  product: Pick<HomepageProductCard, 'commerceChannelCode'>,
): CommerceJourney | null {
  const code = product.commerceChannelCode;
  if (isCommerceJourney(code)) {
    return code;
  }
  return null;
}
