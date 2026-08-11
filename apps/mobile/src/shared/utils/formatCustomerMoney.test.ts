import { formatCustomerMoney } from './formatCustomerMoney';

describe('formatCustomerMoney', () => {
  it('formats whole TZS amounts with grouping', () => {
    expect(formatCustomerMoney('25000.00', 'TZS')).toBe('TZS 25,000');
    expect(formatCustomerMoney(20000, 'TZS')).toBe('TZS 20,000');
    expect(formatCustomerMoney('4500.00', 'TZS')).toBe('TZS 4,500');
  });

  it('keeps fractional amounts when present', () => {
    expect(formatCustomerMoney('25000.50', 'TZS')).toBe('TZS 25,000.50');
  });

  it('returns em dash for empty values', () => {
    expect(formatCustomerMoney(null)).toBe('—');
    expect(formatCustomerMoney(undefined)).toBe('—');
    expect(formatCustomerMoney('')).toBe('—');
  });
});
