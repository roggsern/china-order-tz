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

  it('opens payment screen with orderId only (reuses existing flow)', () => {
    expect(buildPaymentHref({ orderId: 'ord-pending-1' })).toBe(
      '/(app)/payment?orderId=ord-pending-1',
    );
  });
});
