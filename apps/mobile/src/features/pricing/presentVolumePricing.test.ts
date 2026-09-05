import type { VolumePricing, VolumePricingTier } from './mapVolumePricing';
import {
  formatVolumeQuantityRange,
  nextTierHelperMessage,
  presentVolumePricingRows,
} from './presentVolumePricing';

function tier(
  minQuantity: number,
  unitPrice: string,
  extras: Partial<VolumePricingTier> = {},
): VolumePricingTier {
  return {
    min_quantity: minQuantity,
    unit_price: unitPrice,
    type: 'fixed_unit',
    discount_percent: null,
    scope: 'product',
    ...extras,
  };
}

function pricing(overrides: Partial<VolumePricing> = {}): VolumePricing {
  return {
    eligible_quantity: 1,
    aggregates_variants: false,
    current_tier: null,
    next_tier: tier(10, '22000.00'),
    quantity_to_next_tier: 9,
    base_unit_price: '25000.00',
    resolved_unit_price: '25000.00',
    savings_per_unit: '0.00',
    savings_total: '0.00',
    currency: 'TZS',
    tiers: [tier(10, '22000.00'), tier(20, '19500.00')],
    ...overrides,
  };
}

describe('formatVolumeQuantityRange', () => {
  it('formats closed, single, and open-ended ranges', () => {
    expect(formatVolumeQuantityRange(1, 9)).toBe('1–9 pcs');
    expect(formatVolumeQuantityRange(10, 10)).toBe('10 pcs');
    expect(formatVolumeQuantityRange(20, null)).toBe('20+ pcs');
  });
});

describe('presentVolumePricingRows', () => {
  it('hides rows when the server sent no tiers', () => {
    expect(presentVolumePricingRows(pricing({ tiers: [] }))).toEqual([]);
  });

  it('adds an opening band and open-ended final tier for multiple breaks', () => {
    const rows = presentVolumePricingRows(pricing(), 1);
    expect(rows.map((row) => row.quantityLabel)).toEqual([
      '1–9 pcs',
      '10–19 pcs',
      '20+ pcs',
    ]);
    expect(rows[0]?.unitPrice).toBe('25000.00');
    expect(rows[1]?.unitPrice).toBe('22000.00');
    expect(rows[2]?.unitPrice).toBe('19500.00');
    expect(rows[0]?.active).toBe(true);
    expect(rows[1]?.active).toBe(false);
  });

  it('does not invent a duplicate opening row when the first break is already qty 1', () => {
    const rows = presentVolumePricingRows(
      pricing({
        tiers: [tier(1, '25000.00'), tier(10, '22000.00')],
        next_tier: tier(10, '22000.00'),
        quantity_to_next_tier: 9,
      }),
      1,
    );
    expect(rows.map((row) => row.quantityLabel)).toEqual(['1–9 pcs', '10+ pcs']);
    expect(rows.some((row) => row.isOpeningBand)).toBe(false);
  });

  it('skips an opening band when it would duplicate the first tier price', () => {
    const rows = presentVolumePricingRows(
      pricing({
        base_unit_price: '22000.00',
        resolved_unit_price: '22000.00',
        tiers: [tier(10, '22000.00')],
      }),
    );
    expect(rows).toHaveLength(1);
    expect(rows[0]?.quantityLabel).toBe('10+ pcs');
    expect(rows[0]?.isOpeningBand).toBe(false);
  });

  it('highlights threshold - 1, exact threshold, and threshold + 1', () => {
    const schedule = pricing({
      eligible_quantity: 9,
      current_tier: null,
      next_tier: tier(10, '22000.00'),
      quantity_to_next_tier: 1,
    });
    expect(presentVolumePricingRows(schedule, 9).find((row) => row.active)?.quantityLabel).toBe(
      '1–9 pcs',
    );

    const atThreshold = pricing({
      eligible_quantity: 10,
      current_tier: tier(10, '22000.00'),
      next_tier: tier(20, '19500.00'),
      quantity_to_next_tier: 10,
      resolved_unit_price: '22000.00',
    });
    expect(
      presentVolumePricingRows(atThreshold, 10).find((row) => row.active)?.minQuantity,
    ).toBe(10);
    expect(
      presentVolumePricingRows(atThreshold, 11).find((row) => row.active)?.minQuantity,
    ).toBe(10);
  });

  it('highlights the highest tier and invents no further row', () => {
    const top = pricing({
      eligible_quantity: 21,
      current_tier: tier(20, '19500.00'),
      next_tier: null,
      quantity_to_next_tier: null,
      resolved_unit_price: '19500.00',
    });
    const rows = presentVolumePricingRows(top, 21);
    expect(rows[rows.length - 1]?.active).toBe(true);
    expect(rows[rows.length - 1]?.quantityLabel).toBe('20+ pcs');
    expect(rows).toHaveLength(3);
  });

  it('uses the selected quantity immediately when the quote eligible qty is stale', () => {
    const stale = pricing({
      eligible_quantity: 1,
      current_tier: null,
      next_tier: tier(10, '22000.00'),
      quantity_to_next_tier: 9,
    });
    expect(presentVolumePricingRows(stale, 10).find((row) => row.active)?.minQuantity).toBe(10);
  });
});

describe('nextTierHelperMessage', () => {
  it('uses remaining units from the current quantity', () => {
    expect(nextTierHelperMessage(pricing(), 1)).toBe('Add 9 more to get TZS 22,000 each');
    expect(
      nextTierHelperMessage(
        pricing({
          eligible_quantity: 9,
          quantity_to_next_tier: 1,
        }),
        9,
      ),
    ).toBe('Add 1 more to get TZS 22,000 each');
  });

  it('hides the helper at the highest tier or when next_tier is missing', () => {
    expect(
      nextTierHelperMessage(
        pricing({
          eligible_quantity: 20,
          current_tier: tier(20, '19500.00'),
          next_tier: null,
          quantity_to_next_tier: null,
        }),
        20,
      ),
    ).toBeNull();
    expect(
      nextTierHelperMessage(
        pricing({
          next_tier: null,
          quantity_to_next_tier: 4,
        }),
      ),
    ).toBeNull();
  });

  it('formats percent-off helpers from server percent, not local math', () => {
    expect(
      nextTierHelperMessage(
        pricing({
          next_tier: tier(10, '9000.00', {
            type: 'percent_off',
            discount_percent: '10.00',
          }),
          quantity_to_next_tier: 3,
          eligible_quantity: 7,
        }),
        7,
      ),
    ).toBe('Add 3 more to get 10% off');
  });
});
