import { formatCustomerMoney } from '../utils/formatCustomerMoney';
import { colors } from '../theme';

describe('PriceText formatting contract', () => {
  it('formats whole TZS amounts without inventing decimals', () => {
    expect(formatCustomerMoney(25000, 'TZS')).toBe('TZS 25,000');
  });

  it('uses brand primary gold for price typography token', () => {
    expect(colors.primary).toBe('#c9a227');
  });
});
