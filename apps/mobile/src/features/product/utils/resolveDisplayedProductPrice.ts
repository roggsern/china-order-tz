import type {
  CatalogProductDetail,
  ProductConfiguration,
  ProductQuote,
} from '../models/types';

export type DisplayedProductPrice = {
  amount: string | number | null;
  currency: string | null;
  source: 'base' | 'quote' | 'configuration' | 'pending';
};

/**
 * Resolve PDP display price from server sources only.
 * Never invents variant math. Pending while config/quote refetch.
 */
export function resolveDisplayedProductPrice(params: {
  product: CatalogProductDetail;
  configuration?: ProductConfiguration | null;
  configurationLoading?: boolean;
  quote?: ProductQuote | null;
  quoteLoading?: boolean;
}): DisplayedProductPrice {
  if (params.configurationLoading) {
    return { amount: null, currency: null, source: 'pending' };
  }

  const config = params.configuration;
  if (config?.hasConfigurations) {
    if (!config.isComplete || !config.matchedConfigurationId) {
      return {
        amount: params.product.price,
        currency: null,
        source: 'base',
      };
    }
    if (params.quoteLoading) {
      return { amount: null, currency: null, source: 'pending' };
    }
    if (params.quote?.unitPrice != null) {
      return {
        amount: params.quote.unitPrice,
        currency: params.quote.currency,
        source: 'quote',
      };
    }
    if (config.matchedUnitPrice != null) {
      return {
        amount: config.matchedUnitPrice,
        currency: null,
        source: 'configuration',
      };
    }
    return {
      amount: params.product.price,
      currency: null,
      source: 'base',
    };
  }

  return {
    amount: params.product.price,
    currency: null,
    source: 'base',
  };
}
