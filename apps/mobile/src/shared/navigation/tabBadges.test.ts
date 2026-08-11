import { formatTabBadgeCount, countPayableOrders } from './tabBadges';

describe('tabBadges', () => {
  it('formats badge counts for the tab bar', () => {
    expect(formatTabBadgeCount(0)).toBeUndefined();
    expect(formatTabBadgeCount(null)).toBeUndefined();
    expect(formatTabBadgeCount(3)).toBe('3');
    expect(formatTabBadgeCount(99)).toBe('99');
    expect(formatTabBadgeCount(100)).toBe('99+');
  });

  it('counts only server-payable order statuses', () => {
    expect(
      countPayableOrders([
        { status: 'pending' },
        { status: 'pending_payment' },
        { status: 'paid' },
        { status: 'processing' },
      ]),
    ).toBe(2);
    expect(countPayableOrders([])).toBe(0);
  });
});
