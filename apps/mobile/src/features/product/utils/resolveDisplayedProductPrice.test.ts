import { resolveDisplayedProductPrice } from './resolveDisplayedProductPrice';
import type {
  CatalogProductDetail,
  ProductConfiguration,
  ProductQuote,
} from '../models/types';

const product = {
  id: 'p1',
  slug: 'widget',
  name: 'Widget',
  price: '10000',
  imageUrl: null,
  images: [],
  videos: [],
  variants: [],
  isPurchasable: true,
  availabilityStatus: 'available',
  inStock: true,
} as CatalogProductDetail;

describe('resolveDisplayedProductPrice', () => {
  it('shows base product price before configuration is complete', () => {
    const configuration = {
      hasConfigurations: true,
      isComplete: false,
      matchedConfigurationId: null,
      matchedUnitPrice: null,
    } as ProductConfiguration;

    expect(
      resolveDisplayedProductPrice({
        product,
        configuration,
        configurationLoading: false,
      }),
    ).toEqual({
      amount: '10000',
      currency: null,
      source: 'base',
    });
  });

  it('shows pending while configuration is refetching (no stale matched price)', () => {
    const configuration = {
      hasConfigurations: true,
      isComplete: true,
      matchedConfigurationId: 'cfg-1',
      matchedUnitPrice: '15000',
    } as ProductConfiguration;

    expect(
      resolveDisplayedProductPrice({
        product,
        configuration,
        configurationLoading: true,
      }),
    ).toEqual({ amount: null, currency: null, source: 'pending' });
  });

  it('displays matched server quote price with currency', () => {
    const configuration = {
      hasConfigurations: true,
      isComplete: true,
      matchedConfigurationId: 'cfg-1',
      matchedUnitPrice: '15000',
    } as ProductConfiguration;
    const quote = {
      unitPrice: '17500',
      currency: 'TZS',
      lineTotal: '17500',
      quantity: 1,
      configurationId: 'cfg-1',
    } as ProductQuote;

    expect(
      resolveDisplayedProductPrice({
        product,
        configuration,
        quote,
        quoteLoading: false,
      }),
    ).toEqual({
      amount: '17500',
      currency: 'TZS',
      source: 'quote',
    });
  });

  it('falls back to configuration matchedUnitPrice when quote absent', () => {
    const configuration = {
      hasConfigurations: true,
      isComplete: true,
      matchedConfigurationId: 'cfg-1',
      matchedUnitPrice: '15000',
    } as ProductConfiguration;

    expect(
      resolveDisplayedProductPrice({
        product,
        configuration,
        quote: null,
        quoteLoading: false,
      }),
    ).toEqual({
      amount: '15000',
      currency: null,
      source: 'configuration',
    });
  });

  it('changing selection does not retain stale configured price while quote loading', () => {
    const configuration = {
      hasConfigurations: true,
      isComplete: true,
      matchedConfigurationId: 'cfg-2',
      matchedUnitPrice: null,
    } as ProductConfiguration;

    expect(
      resolveDisplayedProductPrice({
        product,
        configuration,
        quoteLoading: true,
      }),
    ).toEqual({ amount: null, currency: null, source: 'pending' });
  });

  it('currency remains server-owned from quote', () => {
    const configuration = {
      hasConfigurations: true,
      isComplete: true,
      matchedConfigurationId: 'cfg-1',
      matchedUnitPrice: '1',
    } as ProductConfiguration;
    const quote = {
      unitPrice: '2',
      currency: 'USD',
      lineTotal: '2',
      quantity: 1,
      configurationId: 'cfg-1',
    } as ProductQuote;

    const displayed = resolveDisplayedProductPrice({
      product,
      configuration,
      quote,
    });
    expect(displayed.currency).toBe('USD');
    expect(displayed.source).toBe('quote');
  });

  it('uses server quote unit price for simple products', () => {
    const quote = {
      unitPrice: '8000.00',
      currency: 'TZS',
      lineTotal: '16000.00',
      quantity: 2,
      configurationId: null,
      volumePricing: null,
    } as ProductQuote;

    expect(
      resolveDisplayedProductPrice({
        product,
        configuration: {
          hasConfigurations: false,
          isComplete: true,
          matchedConfigurationId: null,
          matchedUnitPrice: null,
        } as ProductConfiguration,
        quote,
        quoteLoading: false,
      }),
    ).toEqual({
      amount: '8000.00',
      currency: 'TZS',
      source: 'quote',
    });
  });
});
