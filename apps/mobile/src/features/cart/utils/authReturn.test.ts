import {
  buildLoginHref,
  buildRegisterHref,
  sanitizeAuthReturnTo,
} from './authReturn';
import { buildPaymentHref } from '@/src/features/payments/utils/paymentRoutes';
import { buildProductHref } from '@/src/features/product/map/journeyRoutes';

describe('auth return continuity', () => {
  it('valid internal route accepted', () => {
    expect(sanitizeAuthReturnTo('/(app)/checkout')).toBe('/(app)/checkout');
  });

  it('valid internal route with query accepted', () => {
    const payment = buildPaymentHref({
      orderId: 'ord-1',
      paymentTransactionId: 'txn-1',
      checkoutSessionId: 'sess-1',
    });
    expect(sanitizeAuthReturnTo(payment)).toBe(payment);
    const href = buildLoginHref(payment);
    expect(decodeURIComponent(href)).toContain('orderId=ord-1');
    expect(decodeURIComponent(href)).toContain('paymentTransactionId=txn-1');
  });

  it('registration preserves checkout returnTo', () => {
    const checkout = '/(app)/checkout';
    expect(buildRegisterHref(checkout)).toContain(encodeURIComponent(checkout));
  });

  it('preserves product journey/store context', () => {
    const tz = buildProductHref({
      slug: 'widget',
      journey: 'TZ_LOCAL',
      storeSlug: 'zion',
    });
    expect(sanitizeAuthReturnTo(tz)).toBe(tz);
  });

  it('external rejected', () => {
    expect(sanitizeAuthReturnTo('https://evil.example')).toBeNull();
    expect(buildLoginHref('https://evil.example')).toBe('/(auth)/login');
  });

  it('protocol-relative //host rejected', () => {
    expect(sanitizeAuthReturnTo('//evil.example')).toBeNull();
    expect(sanitizeAuthReturnTo('/(app)//evil')).toBeNull();
  });

  it('../ traversal rejected', () => {
    expect(sanitizeAuthReturnTo('/(app)/../secret')).toBeNull();
    expect(sanitizeAuthReturnTo('/(app)/orders/../checkout')).toBeNull();
  });

  it('encoded traversal rejected', () => {
    expect(sanitizeAuthReturnTo('/(app)/%2e%2e/secret')).toBeNull();
    expect(
      sanitizeAuthReturnTo(encodeURIComponent('/(app)/../secret')),
    ).toBeNull();
  });

  it('auth routes and malformed rejected', () => {
    expect(sanitizeAuthReturnTo('/(auth)/login')).toBeNull();
    expect(sanitizeAuthReturnTo('')).toBeNull();
    expect(sanitizeAuthReturnTo('/app/home')).toBeNull();
  });
});
