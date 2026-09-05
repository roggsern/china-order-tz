import { render, screen } from '@testing-library/react-native';
import type { VolumePricing } from '@/src/features/pricing/mapVolumePricing';
import { BulkPricingCard } from './BulkPricingCard';

function pricing(overrides: Partial<VolumePricing> = {}): VolumePricing {
  return {
    eligible_quantity: 1,
    aggregates_variants: false,
    current_tier: null,
    next_tier: {
      min_quantity: 10,
      unit_price: '22000.00',
      type: 'fixed_unit',
      discount_percent: null,
      scope: 'product',
    },
    quantity_to_next_tier: 9,
    base_unit_price: '25000.00',
    resolved_unit_price: '25000.00',
    savings_per_unit: '0.00',
    savings_total: '0.00',
    currency: 'TZS',
    tiers: [
      {
        min_quantity: 10,
        unit_price: '22000.00',
        type: 'fixed_unit',
        discount_percent: null,
        scope: 'product',
      },
      {
        min_quantity: 20,
        unit_price: '19500.00',
        type: 'fixed_unit',
        discount_percent: null,
        scope: 'product',
      },
    ],
    ...overrides,
  };
}

describe('BulkPricingCard', () => {
  it('hides when there is no schedule', async () => {
    await render(<BulkPricingCard pricing={null} />);
    expect(screen.queryByLabelText('Volume pricing')).toBeNull();
  });

  it('hides when the mapped schedule has no tiers', async () => {
    await render(<BulkPricingCard pricing={pricing({ tiers: [] })} />);
    expect(screen.queryByLabelText('Volume pricing')).toBeNull();
  });

  it('renders ranges, currency, and the next-tier helper', async () => {
    await render(
      <BulkPricingCard pricing={pricing()} quantity={1} showCartAuthorityNote />,
    );
    expect(screen.getByText('Volume pricing')).toBeTruthy();
    expect(screen.getByText('1–9 pcs')).toBeTruthy();
    expect(screen.getByText('10–19 pcs')).toBeTruthy();
    expect(screen.getByText('20+ pcs')).toBeTruthy();
    expect(screen.getAllByText('TZS 25,000').length).toBeGreaterThan(0);
    expect(screen.getByText('TZS 22,000')).toBeTruthy();
    expect(screen.getByText('TZS 19,500')).toBeTruthy();
    expect(screen.getByText('Add 9 more to get TZS 22,000 each')).toBeTruthy();
    expect(screen.getByText('Final price is confirmed in cart.')).toBeTruthy();
  });

  it('does not show a next-tier helper at the highest tier', async () => {
    await render(
      <BulkPricingCard
        pricing={pricing({
          eligible_quantity: 20,
          current_tier: {
            min_quantity: 20,
            unit_price: '19500.00',
            type: 'fixed_unit',
            discount_percent: null,
            scope: 'product',
          },
          next_tier: null,
          quantity_to_next_tier: null,
          resolved_unit_price: '19500.00',
        })}
        quantity={20}
      />,
    );
    expect(screen.queryByText(/Add .+ more/)).toBeNull();
  });

  it('shows a lightweight loading state without blocking the rest of the PDP', async () => {
    await render(<BulkPricingCard pricing={null} loading />);
    expect(screen.getByText('Checking volume prices…')).toBeTruthy();
  });

  it('shows a graceful schedule error without inventing tiers', async () => {
    await render(<BulkPricingCard pricing={null} error />);
    expect(
      screen.getByText('Volume prices unavailable. Final price is confirmed in cart.'),
    ).toBeTruthy();
  });
});
