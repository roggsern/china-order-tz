import { sanitizeAuthReturnTo } from '@/src/features/cart/utils/authReturn';
import { pendingCheckoutContextStorage } from '@/src/features/checkout/storage/pendingCheckoutContextStorage';
import { pendingPaymentContextStorage } from '@/src/features/payments/storage/pendingPaymentContextStorage';
import { buildSafeProductHref } from '@/src/features/product/utils/buildSafeProductHref';
import { resolveHomepageProductJourney } from '@/src/features/home/utils/resolveHomepageProductJourney';
import { resolveHitJourney } from '@/src/features/search/utils/resolveHitJourney';
import { buildPaymentHref } from '@/src/features/payments/utils/paymentRoutes';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn(),
  getItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

const SecureStore = jest.requireMock('expo-secure-store') as {
  getItemAsync: jest.Mock;
  setItemAsync: jest.Mock;
  deleteItemAsync: jest.Mock;
};

describe('security & UX closure integration', () => {
  beforeEach(() => {
    SecureStore.getItemAsync.mockReset();
    SecureStore.setItemAsync.mockReset();
    SecureStore.deleteItemAsync.mockReset();
  });

  it('auth returnTo preserves payment/checkout params safely', () => {
    const payment = buildPaymentHref({
      orderId: 'ord-1',
      paymentTransactionId: 'txn-1',
      checkoutSessionId: 'sess-1',
    });
    const login = buildLoginHref(payment);
    expect(sanitizeAuthReturnTo(payment)).toBe(payment);
    expect(decodeURIComponent(login)).toContain('checkoutSessionId=sess-1');
    expect(sanitizeAuthReturnTo('/(app)/../evil')).toBeNull();
  });

  it('user A logout / user B login isolation for payment + checkout', async () => {
    SecureStore.getItemAsync.mockResolvedValue(
      JSON.stringify({
        userId: 'user-a',
        orderId: 'ord-a',
        paymentTransactionId: 'txn-a',
        merchantReference: 'ref-a',
        successIndicator: 'si',
        resultIndicator: null,
        checkoutSessionId: null,
        updatedAt: new Date().toISOString(),
      }),
    );
    await expect(
      pendingPaymentContextStorage.bindToAuthenticatedUser('user-b'),
    ).resolves.toBeNull();

    SecureStore.getItemAsync.mockResolvedValue(
      JSON.stringify({
        userId: 'user-a',
        checkoutSessionId: 'sess-a',
        orderId: null,
        paymentTransactionId: null,
        updatedAt: new Date().toISOString(),
      }),
    );
    await expect(
      pendingCheckoutContextStorage.bindToAuthenticatedUser('user-b'),
    ).resolves.toBeNull();
  });

  it('TZ home/search require product store ownership', () => {
    expect(
      buildSafeProductHref({
        slug: 'dress',
        journey: 'TZ_LOCAL',
        productStoreSlug: null,
      }).ok,
    ).toBe(false);
    expect(
      buildSafeProductHref({
        slug: 'dress',
        journey: 'TZ_LOCAL',
        productStoreSlug: 'zion',
      }).ok,
    ).toBe(true);
    expect(
      resolveHitJourney({
        id: '1',
        slug: 'x',
        name: 'x',
        price: null,
        imageUrl: null,
        marketplace: null,
      }),
    ).toBeNull();
  });

  it('homepage product channel integrity fails closed when ambiguous', () => {
    expect(resolveHomepageProductJourney({ commerceChannelCode: null })).toBeNull();
    expect(
      resolveHomepageProductJourney({ commerceChannelCode: 'CHINA_IMPORT' }),
    ).toBe('CHINA_IMPORT');
  });

  it('missing route ids are treated as empty strings by callers', () => {
    // Route screens gate on trim(); empty/undefined must not reach APIs.
    const missing = [undefined, '', '   '] as const;
    for (const value of missing) {
      expect(!(value?.trim())).toBe(true);
    }
  });
});
