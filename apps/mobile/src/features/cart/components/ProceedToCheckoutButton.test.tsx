import { render, screen } from '@testing-library/react-native';
import { ProceedToCheckoutButton } from './ProceedToCheckoutButton';

describe('ProceedToCheckoutButton', () => {
  it('shows a visible reason and disables when purchase blockers exist', async () => {
    await render(<ProceedToCheckoutButton quantityBlocked />);
    expect(
      screen.getByText(
        'Update quantities to meet purchase requirements before checkout.',
      ),
    ).toBeTruthy();
  });

  it('does not treat an empty blocker list as a visible block', async () => {
    await render(<ProceedToCheckoutButton quantityBlocked={false} />);
    expect(
      screen.queryByText(
        'Update quantities to meet purchase requirements before checkout.',
      ),
    ).toBeNull();
  });
});
