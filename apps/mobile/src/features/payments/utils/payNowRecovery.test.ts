import {
  isPaymentInProgressError,
  paymentInProgressCustomerMessage,
  recoveryFromStartError,
  resolvePayNowView,
  resolveRefreshedTransactionView,
} from './payNowRecovery';

describe('Pay Now recovery', () => {
  it('opens the method selector when the order is payable and has no active attempt', () => {
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'pending_payment',
        paymentStatus: 'pending',
      }),
    ).toEqual({ kind: 'selector' });
  });

  it('recovers a pending active transaction without starting a new provider', () => {
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'pending_payment',
        paymentStatus: 'initiated',
        activeTransaction: {
          id: 'txn-pending-1',
          status: 'pending',
          provider: 'nmb',
        },
      }),
    ).toEqual({
      kind: 'recovery',
      transaction: {
        id: 'txn-pending-1',
        status: 'pending',
        provider: 'nmb',
      },
    });
  });

  it('recovers a processing active transaction discovered from the backend', () => {
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'pending_payment',
        paymentStatus: 'initiated',
        activeTransaction: {
          id: 'txn-snippe-1',
          status: 'processing',
          provider: 'snippe',
        },
      }),
    ).toEqual({
      kind: 'recovery',
      transaction: {
        id: 'txn-snippe-1',
        status: 'processing',
        provider: 'snippe',
      },
    });
  });

  it('does not offer another start after a successful payment', () => {
    expect(resolveRefreshedTransactionView('successful')).toBe('paid');
    expect(
      resolvePayNowView({
        canPay: false,
        orderStatus: 'paid',
        paymentStatus: 'paid',
      }),
    ).toEqual({ kind: 'paid' });
  });

  it('returns to method selection after a failed attempt', () => {
    expect(resolveRefreshedTransactionView('failed')).toBe('selector');
  });

  it('returns to method selection after a cancelled or voided attempt', () => {
    expect(resolveRefreshedTransactionView('cancelled')).toBe('selector');
    expect(resolveRefreshedTransactionView('voided')).toBe('selector');
    expect(resolveRefreshedTransactionView('expired')).toBe('selector');
  });

  it('keeps genuinely processing attempts in recovery', () => {
    expect(resolveRefreshedTransactionView('processing')).toBe('recovery');
    expect(resolveRefreshedTransactionView('pending')).toBe('recovery');
  });

  it('treats a cancelled order plus a processing transaction as not payable', () => {
    expect(
      resolvePayNowView({
        canPay: false,
        orderStatus: 'cancelled',
        paymentStatus: 'cancelled',
        activeTransaction: {
          id: 'txn-stale-1',
          status: 'processing',
          provider: 'nmb',
        },
      }),
    ).toEqual({ kind: 'not_payable', reason: 'cancelled' });

    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'cancelled',
        paymentStatus: 'initiated',
        activeTransaction: {
          id: 'txn-stale-2',
          status: 'processing',
          provider: 'snippe',
        },
      }),
    ).toEqual({ kind: 'not_payable', reason: 'cancelled' });
  });

  it('treats a refunded order plus a processing transaction as not payable', () => {
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'refunded',
        paymentStatus: 'refunded',
        activeTransaction: {
          id: 'txn-stale-3',
          status: 'processing',
          provider: 'nmb',
        },
      }),
    ).toEqual({ kind: 'not_payable', reason: 'cancelled' });
  });

  it('does not invent recovery from missing local transaction state', () => {
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'pending_payment',
        paymentStatus: 'pending',
        activeTransaction: null,
      }),
    ).toEqual({ kind: 'selector' });
  });

  it('maps the raw active-payment error onto recovery UX', () => {
    const error = {
      code: 'payment_in_progress',
      message: 'An active payment is already in progress for this order.',
      paymentTransactionId: 'txn-active-1',
      paymentTransactionStatus: 'processing',
      provider: 'snippe',
    };

    expect(isPaymentInProgressError(error)).toBe(true);
    expect(recoveryFromStartError(error)).toEqual({
      id: 'txn-active-1',
      status: 'processing',
      provider: 'snippe',
    });
    expect(paymentInProgressCustomerMessage()).toBe(
      'A payment request is already in progress for this order.',
    );
    expect(paymentInProgressCustomerMessage()).not.toMatch(/payment_in_progress/i);
  });

  it('still maps the legacy message when the contract code is missing', () => {
    const error = {
      message: 'An active payment is already in progress for this order.',
    };

    expect(isPaymentInProgressError(error)).toBe(true);
    expect(recoveryFromStartError(error)).toBeNull();
  });
});
