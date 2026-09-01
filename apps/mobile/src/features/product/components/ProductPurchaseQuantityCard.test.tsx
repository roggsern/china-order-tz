import { render, screen } from '@testing-library/react-native';
import { ProductPurchaseQuantityCard } from './ProductPurchaseQuantityCard';
import { CartPurchaseQuantityBanner } from '@/src/features/cart/components/CartPurchaseQuantityBanner';
import type { PurchaseQuantityPresentation } from '@/src/features/purchasing/purchaseQuantity';

const moqOnly: PurchaseQuantityPresentation = {
  minimum_quantity: 6,
  increment: null,
  eligible_quantity: 2,
  aggregates_variants: false,
  minimum_satisfied: false,
  increment_satisfied: true,
  quantity_to_minimum: 4,
  next_legal_quantity: 6,
  construction_complete: false,
  blocks_checkout: true,
};

describe('purchase quantity components', () => {
  it('renders no PDP section when purchase_quantity is absent', async () => {
    await render(<ProductPurchaseQuantityCard presentation={null} />);
    expect(screen.queryByLabelText('Purchase requirements')).toBeNull();
    expect(screen.queryByText('Minimum order quantity: 6')).toBeNull();
  });

  it('exposes readable purchase requirement copy, not color-only status', async () => {
    await render(<ProductPurchaseQuantityCard presentation={moqOnly} />);
    expect(screen.getByLabelText('Purchase requirements')).toBeTruthy();
    expect(screen.getByText('Minimum order quantity: 6')).toBeTruthy();
    expect(screen.getByText('Add 4 more to reach the minimum.')).toBeTruthy();
  });

  it('shows compact cart blocker copy that wraps as text', async () => {
    await render(
      <CartPurchaseQuantityBanner
        blocker={{
          product_id: 'p1',
          minimum_quantity: 6,
          increment: 3,
          eligible_quantity: 7,
          minimum_satisfied: true,
          increment_satisfied: false,
          quantity_to_minimum: 0,
          next_legal_quantity: 9,
          blocks_checkout: true,
        }}
        aggregatesVariants
      />,
    );
    expect(
      screen.getByText('Quantity 7 is not an allowed total.'),
    ).toBeTruthy();
    expect(screen.getByText('Next allowed quantity: 9.')).toBeTruthy();
    expect(screen.getByText('Any variant counts toward this total.')).toBeTruthy();
  });
});
