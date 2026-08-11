import type { CommerceJourney } from '@/src/shared/types/commerce';
import type { SearchHit } from '../models/types';

/**
 * Resolve product journey from search hit identity only.
 * Never invents China for missing/unknown marketplace.
 */
export function resolveHitJourney(hit: SearchHit): CommerceJourney | null {
  if (hit.marketplace === 'tz' || hit.commerceChannelCode === 'TZ_LOCAL') {
    return 'TZ_LOCAL';
  }
  if (hit.marketplace === 'china' || hit.commerceChannelCode === 'CHINA_IMPORT') {
    return 'CHINA_IMPORT';
  }
  return null;
}
