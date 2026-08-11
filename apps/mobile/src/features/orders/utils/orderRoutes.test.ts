import { buildLoginHref, sanitizeAuthReturnTo } from '@/src/features/cart/utils/authReturn';
import {
  buildOrderDetailHref,
  buildOrderTrackingHref,
  buildOrdersListHref,
  buildPostPaymentOrdersHref,
} from './orderRoutes';

describe('order routes', () => {
  it('builds list, detail, and tracking hrefs', () => {
    expect(buildOrdersListHref()).toBe('/(app)/(tabs)/orders');
    expect(buildOrderDetailHref('ord-1')).toBe('/(app)/orders/ord-1');
    expect(buildOrderTrackingHref('ord-1')).toBe(
      '/(app)/orders/ord-1/tracking',
    );
  });

  it('navigates payment success to order detail when server order id exists', () => {
    expect(buildPostPaymentOrdersHref('ord-99')).toBe('/(app)/orders/ord-99');
    expect(buildPostPaymentOrdersHref(null)).toBe('/(app)/(tabs)/orders');
    expect(buildPostPaymentOrdersHref('  ')).toBe('/(app)/(tabs)/orders');
  });

  it('supports unauthenticated redirect with returnTo orders paths', () => {
    const list = buildOrdersListHref();
    const detail = buildOrderDetailHref('ord-1');

    expect(sanitizeAuthReturnTo(list)).toBe(list);
    expect(sanitizeAuthReturnTo(detail)).toBe(detail);
    expect(buildLoginHref(list)).toContain(encodeURIComponent(list));
    expect(buildLoginHref(detail)).toContain(encodeURIComponent(detail));
  });
});
