import { isOrderPayableFromServer } from './isOrderPayable';
import { buildPaymentHref } from '@/src/features/payments/utils/paymentRoutes';

describe('unpaid order payment resume', () => {
  it('offers continue payment only for server payable statuses', () => {
    expect(isOrderPayableFromServer({ status: 'pending_payment' })).toBe(true);
    expect(isOrderPayableFromServer({ status: 'pending' })).toBe(true);
    expect(isOrderPayableFromServer({ status: 'paid' })).toBe(false);
    expect(isOrderPayableFromServer({ status: 'processing' })).toBe(false);
    expect(isOrderPayableFromServer({ status: 'cancelled' })).toBe(false);
    expect(isOrderPayableFromServer({ status: null })).toBe(false);
    expect(
      isOrderPayableFromServer({ status: 'pending_payment', canPay: true }),
    ).toBe(true);
    expect(
      isOrderPayableFromServer({ status: 'pending_payment', canPay: false }),
    ).toBe(false);
  });

  it('prefers backend can_pay when present', () => {
    expect(
      isOrderPayableFromServer({
        status: 'pending_payment',
        paymentStatus: 'failed',
        canPay: true,
      }),
    ).toBe(true);
    expect(
      isOrderPayableFromServer({
        status: 'pending_payment',
        paymentStatus: 'pending',
        canPay: false,
      }),
    ).toBe(false);
  });

  it('never treats cancelled or refunded orders as payable', () => {
    expect(
      isOrderPayableFromServer({
        status: 'cancelled',
        canPay: true,
        paymentStatus: 'initiated',
      }),
    ).toBe(false);
    expect(
      isOrderPayableFromServer({
        status: 'refunded',
        canPay: true,
        paymentStatus: 'refunded',
      }),
    ).toBe(false);
    expect(
      isOrderPayableFromServer({
        status: 'refund_pending',
        canPay: true,
      }),
    ).toBe(false);
    expect(
      isOrderPayableFromServer({
        status: 'pending_payment',
        paymentStatus: 'refunded',
        canPay: true,
      }),
    ).toBe(false);
  });

  it('never treats paid orders as payable', () => {
    expect(isOrderPayableFromServer({ status: 'paid', canPay: false })).toBe(false);
    expect(
      isOrderPayableFromServer({
        status: 'processing',
        paymentStatus: 'paid',
      }),
    ).toBe(false);
  });

  it('keeps older fixtures that omit can_pay on a safe status fallback', () => {
    expect(isOrderPayableFromServer({ status: 'pending_payment' })).toBe(true);
    expect(isOrderPayableFromServer({ status: 'pending' })).toBe(true);
    expect(isOrderPayableFromServer({ status: 'cancelled' })).toBe(false);
    expect(isOrderPayableFromServer({ status: 'refunded' })).toBe(false);
    expect(isOrderPayableFromServer({ status: 'paid' })).toBe(false);
  });

  it('does not use local transaction state as authority', () => {
    expect(
      isOrderPayableFromServer({
        status: 'pending_payment',
        canPay: true,
      }),
    ).toBe(true);
  });

  it('opens payment screen with orderId only (reuses existing flow)', () => {
    expect(buildPaymentHref({ orderId: 'ord-pending-1' })).toBe(
      '/(app)/payment?orderId=ord-pending-1',
    );
  });
});
