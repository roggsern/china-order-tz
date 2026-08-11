import { isCommerceJourney } from '@/src/shared/types/commerce';

/**
 * Friendly journey label from server commerce_channel_code.
 * Does not invent mixing rules — display only.
 */
export function journeyLabelFromChannel(code: string | null | undefined): string {
  if (code === 'TZ_LOCAL') return 'Buy from TZ';
  if (code === 'CHINA_IMPORT') return 'Order from China';
  if (typeof code === 'string' && isCommerceJourney(code)) {
    return code === 'TZ_LOCAL' ? 'Buy from TZ' : 'Order from China';
  }
  return 'Marketplace';
}
