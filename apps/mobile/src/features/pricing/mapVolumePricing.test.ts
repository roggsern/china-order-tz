import { mapVolumePricing } from './mapVolumePricing';

describe('mapVolumePricing', () => {
  it('does not compute payable unit prices from percent_off locally', () => {
    const mapped = mapVolumePricing({
      eligible_quantity: 10,
      aggregates_variants: false,
      current_tier: {
        min_quantity: 10,
        unit_price: '9000.00',
        type: 'percent_off',
        discount_percent: '10.00',
        scope: 'product',
      },
      next_tier: null,
      quantity_to_next_tier: null,
      base_unit_price: '10000.00',
      resolved_unit_price: '9000.00',
      savings_per_unit: '1000.00',
      savings_total: '10000.00',
      currency: 'TZS',
      tiers: [
        {
          min_quantity: 10,
          unit_price: '9000.00',
          type: 'percent_off',
          discount_percent: '10.00',
          scope: 'product',
        },
      ],
    });

    expect(mapped?.tiers[0]?.unit_price).toBe('9000.00');
    expect(mapped?.resolved_unit_price).toBe('9000.00');
    expect(mapped?.tiers[0]?.unit_price).not.toBe(String(10000 * 0.9));
  });
});
