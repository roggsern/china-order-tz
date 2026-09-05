import type { ProductQuote } from '../models/types';
import type { VolumePricing } from '@/src/features/pricing/mapVolumePricing';
import {
  quoteMatchesPdpSelection,
  resolvePdpVolumePricing,
} from './resolvePdpVolumePricing';

const volume: VolumePricing = {
  eligible_quantity: 1,
  aggregates_variants: false,
  current_tier: null,
  next_tier: {
    min_quantity: 10,
    unit_price: '8000.00',
    type: 'fixed_unit',
    discount_percent: null,
    scope: 'configuration',
  },
  quantity_to_next_tier: 9,
  base_unit_price: '10000.00',
  resolved_unit_price: '10000.00',
  savings_per_unit: '0.00',
  savings_total: '0.00',
  currency: 'TZS',
  tiers: [
    {
      min_quantity: 10,
      unit_price: '8000.00',
      type: 'fixed_unit',
      discount_percent: null,
      scope: 'configuration',
    },
  ],
};

const redQuote: ProductQuote = {
  productId: 'p1',
  configurationId: 'cfg-red',
  quantity: 1,
  currency: 'TZS',
  unitPrice: '10000.00',
  lineTotal: '10000.00',
  volumePricing: volume,
  purchaseQuantity: null,
};

const blueVolume: VolumePricing = {
  ...volume,
  tiers: [
    {
      min_quantity: 5,
      unit_price: '7000.00',
      type: 'fixed_unit',
      discount_percent: null,
      scope: 'configuration',
    },
  ],
};

describe('resolvePdpVolumePricing', () => {
  it('returns no schedule when the quote is disabled or configuration is loading', () => {
    expect(
      resolvePdpVolumePricing({
        quote: redQuote,
        quoteEnabled: false,
        configurationLoading: false,
        configurationId: 'cfg-red',
      }),
    ).toBeNull();
    expect(
      resolvePdpVolumePricing({
        quote: redQuote,
        quoteEnabled: true,
        configurationLoading: true,
        configurationId: 'cfg-red',
      }),
    ).toBeNull();
  });

  it('does not display a previous variant schedule after the selection changes', () => {
    expect(
      resolvePdpVolumePricing({
        quote: redQuote,
        quoteEnabled: true,
        configurationLoading: false,
        configurationId: 'cfg-blue',
      }),
    ).toBeNull();
  });

  it('keeps the matching configuration schedule while quantity may still be refetching', () => {
    expect(
      resolvePdpVolumePricing({
        quote: { ...redQuote, quantity: 1 },
        quoteEnabled: true,
        configurationLoading: false,
        configurationId: 'cfg-red',
      }),
    ).toEqual(volume);
  });

  it('does not invent a schedule when the server omitted volume_pricing', () => {
    expect(
      resolvePdpVolumePricing({
        quote: { ...redQuote, volumePricing: null },
        quoteEnabled: true,
        configurationLoading: false,
        configurationId: 'cfg-red',
      }),
    ).toBeNull();
  });
});

describe('quoteMatchesPdpSelection', () => {
  it('requires the same configuration and quantity before using quote as payable preview', () => {
    expect(
      quoteMatchesPdpSelection({
        quote: redQuote,
        quoteEnabled: true,
        quantity: 1,
        configurationId: 'cfg-red',
      }),
    ).toBe(true);
    expect(
      quoteMatchesPdpSelection({
        quote: redQuote,
        quoteEnabled: true,
        quantity: 10,
        configurationId: 'cfg-red',
      }),
    ).toBe(false);
    expect(
      quoteMatchesPdpSelection({
        quote: { ...redQuote, volumePricing: blueVolume, configurationId: 'cfg-blue' },
        quoteEnabled: true,
        quantity: 1,
        configurationId: 'cfg-red',
      }),
    ).toBe(false);
  });
});
