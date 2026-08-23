import {
  shouldRefreshActivePaymentOnResume,
  shouldRunForegroundCommerceRefresh,
} from './foregroundCommerceRefresh';

describe('foregroundCommerceRefresh', () => {
  it('runs a targeted refresh when returning from background', () => {
    expect(shouldRunForegroundCommerceRefresh('background', 'active')).toBe(true);
    expect(shouldRunForegroundCommerceRefresh('inactive', 'active')).toBe(true);
    expect(shouldRunForegroundCommerceRefresh('active', 'active')).toBe(false);
    expect(shouldRunForegroundCommerceRefresh(null, 'active')).toBe(false);
  });

  it('refreshes an active payment transaction on resume, not a new start', () => {
    expect(
      shouldRefreshActivePaymentOnResume({
        viewKind: 'recovery',
        transactionId: 'txn-1',
      }),
    ).toBe(true);
    expect(
      shouldRefreshActivePaymentOnResume({
        viewKind: 'processing',
        transactionId: 'txn-1',
      }),
    ).toBe(true);
    expect(
      shouldRefreshActivePaymentOnResume({
        viewKind: 'selector',
        transactionId: 'txn-1',
      }),
    ).toBe(false);
    expect(
      shouldRefreshActivePaymentOnResume({
        viewKind: 'recovery',
        transactionId: null,
      }),
    ).toBe(false);
  });
});
