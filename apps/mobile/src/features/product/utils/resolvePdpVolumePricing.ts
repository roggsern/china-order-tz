import type { VolumePricing } from '@/src/features/pricing/mapVolumePricing';
import type { ProductQuote } from '../models/types';

/**
 * PDP may keep a same-configuration quote while quantity refetches.
 * Drop the schedule as soon as configuration is unknown or belongs to another variant.
 */
export function resolvePdpVolumePricing(params: {
  quote: ProductQuote | null;
  quoteEnabled: boolean;
  configurationLoading: boolean;
  configurationId: string | null;
}): VolumePricing | null {
  if (!params.quoteEnabled || params.configurationLoading) {
    return null;
  }
  if (!params.quote?.volumePricing) {
    return null;
  }
  if ((params.quote.configurationId ?? null) !== (params.configurationId ?? null)) {
    return null;
  }
  return params.quote.volumePricing;
}

/** Payable preview may use the quote only when it is for this configuration + quantity. */
export function quoteMatchesPdpSelection(params: {
  quote: ProductQuote | null;
  quoteEnabled: boolean;
  quantity: number;
  configurationId: string | null;
}): boolean {
  if (!params.quoteEnabled || !params.quote) {
    return false;
  }
  if (params.quote.quantity !== params.quantity) {
    return false;
  }
  return (params.quote.configurationId ?? null) === (params.configurationId ?? null);
}
