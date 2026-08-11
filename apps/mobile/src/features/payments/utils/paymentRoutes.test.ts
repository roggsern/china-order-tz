import { buildLoginHref, sanitizeAuthReturnTo } from '@/src/features/cart/utils/authReturn';
import { buildPaymentHref, parsePaymentHrefParams } from './paymentRoutes';

describe('payment routes + auth context preservation', () => {
  it('builds payment href with orderId for unpaid resume', () => {
    expect(buildPaymentHref({ orderId: 'ord-42' })).toBe(
      '/(app)/payment?orderId=ord-42',
    );
  });

  it('preserves checkoutSessionId and paymentTransactionId in href', () => {
    const href = buildPaymentHref({
      orderId: 'ord-1',
      checkoutSessionId: 'sess-1',
      paymentTransactionId: 'txn-1',
    });
    expect(href).toContain('orderId=ord-1');
    expect(href).toContain('checkoutSessionId=sess-1');
    expect(href).toContain('paymentTransactionId=txn-1');

    const parsed = parsePaymentHrefParams(href);
    expect(parsed).toEqual({
      orderId: 'ord-1',
      checkoutSessionId: 'sess-1',
      paymentTransactionId: 'txn-1',
    });
  });

  it('auth redirect preserves full payment query in returnTo', () => {
    const paymentHref = buildPaymentHref({
      orderId: 'ord-7',
      checkoutSessionId: 'sess-7',
    });
    const loginHref = buildLoginHref(paymentHref);

    expect(sanitizeAuthReturnTo(paymentHref)).toBe(paymentHref);
    expect(loginHref).toContain('returnTo=');
    expect(loginHref).toContain(encodeURIComponent(paymentHref));

    const returnToParam = new URLSearchParams(
      loginHref.split('?')[1] ?? '',
    ).get('returnTo');
    expect(sanitizeAuthReturnTo(returnToParam)).toBe(paymentHref);
  });
});
