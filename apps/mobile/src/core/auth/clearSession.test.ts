import { QueryClient } from '@tanstack/react-query';
import {
  clearSession,
  clearSessionOnAuthFailure,
  clearSessionOnLogout,
} from '@/src/core/auth/clearSession';
import { useAuthStore } from '@/src/core/auth/authStore';
import { useJourneyStore } from '@/src/core/auth/journeyStore';
import {
  clearUserSensitiveQueryCaches,
  registerAppQueryClient,
  USER_SENSITIVE_QUERY_ROOTS,
} from '@/src/core/api/queryClientRegistry';
import { secureTokenStorage } from '@/src/core/storage';
import { useCatalogUiStore } from '@/src/features/product/state/catalogUiStore';
import { pendingCheckoutContextStorage } from '@/src/features/checkout/storage/pendingCheckoutContextStorage';
import { pendingPaymentContextStorage } from '@/src/features/payments/storage/pendingPaymentContextStorage';

jest.mock('@/src/core/storage', () => ({
  secureTokenStorage: {
    readToken: jest.fn(),
    clearToken: jest.fn(),
    saveToken: jest.fn(),
  },
}));

jest.mock('@/src/features/payments/storage/pendingPaymentContextStorage', () => ({
  pendingPaymentContextStorage: {
    save: jest.fn(),
    read: jest.fn(),
    readValid: jest.fn(),
    merge: jest.fn(),
    clear: jest.fn(),
  },
}));

jest.mock('@/src/features/checkout/storage/pendingCheckoutContextStorage', () => ({
  pendingCheckoutContextStorage: {
    save: jest.fn(),
    read: jest.fn(),
    readValid: jest.fn(),
    clear: jest.fn(),
  },
}));

const mockClearToken = secureTokenStorage.clearToken as jest.Mock;
const mockClearPaymentContext = pendingPaymentContextStorage.clear as jest.Mock;
const mockClearCheckoutContext = pendingCheckoutContextStorage.clear as jest.Mock;

describe('clearSession policies', () => {
  let queryClient: QueryClient;

  beforeEach(() => {
    mockClearToken.mockReset();
    mockClearPaymentContext.mockReset();
    mockClearCheckoutContext.mockReset();
    queryClient = new QueryClient();
    registerAppQueryClient(queryClient);

    useAuthStore.setState({
      status: 'authenticated',
      user: { id: 'u1', name: 'Ada', email: 'ada@example.com' },
      bootstrapStatus: 'complete',
    });
    useJourneyStore.setState({ journey: 'TZ_LOCAL' });
    useCatalogUiStore.setState({ selectedTzStoreSlug: 'zion' });

    queryClient.setQueryData(['cart', 'current'], { id: 'cart-a', items: [1] });
    queryClient.setQueryData(['orders', 'list', 'all'], { orders: [{ id: 'o1' }] });
    queryClient.setQueryData(['checkout', 'prepare'], { ready: true });
    queryClient.setQueryData(['payments', 'methods'], { methods: [] });
    queryClient.setQueryData(['storefront', 'homepage', 'TZ_LOCAL'], { ok: true });
  });

  it('auth 401 / default clearSession preserves payment and checkout context', async () => {
    await clearSessionOnAuthFailure();

    expect(mockClearToken).toHaveBeenCalled();
    expect(useAuthStore.getState().status).toBe('unauthenticated');
    expect(mockClearPaymentContext).not.toHaveBeenCalled();
    expect(mockClearCheckoutContext).not.toHaveBeenCalled();
    expect(useJourneyStore.getState().journey).toBe('TZ_LOCAL');
    expect(useCatalogUiStore.getState().selectedTzStoreSlug).toBe('zion');
  });

  it('default clearSession is auth-failure safe (preserves recovery contexts)', async () => {
    await clearSession();
    expect(mockClearPaymentContext).not.toHaveBeenCalled();
    expect(mockClearCheckoutContext).not.toHaveBeenCalled();
  });

  it('explicit logout clears payment + checkout recovery and resets journey', async () => {
    await clearSessionOnLogout();

    expect(mockClearPaymentContext).toHaveBeenCalled();
    expect(mockClearCheckoutContext).toHaveBeenCalled();
    expect(useJourneyStore.getState().journey).toBe('CHINA_IMPORT');
    expect(useCatalogUiStore.getState().selectedTzStoreSlug).toBeNull();
  });

  it('clears user-sensitive query caches so expired token is not paired with stale data', async () => {
    await clearSessionOnAuthFailure();

    for (const root of USER_SENSITIVE_QUERY_ROOTS) {
      expect(queryClient.getQueryCache().findAll({ queryKey: [root] })).toHaveLength(0);
    }
    expect(
      queryClient.getQueryData(['storefront', 'homepage', 'TZ_LOCAL']),
    ).toEqual({ ok: true });
  });
});

describe('clearUserSensitiveQueryCaches isolation', () => {
  it('removes User A commerce caches before User B can read them', () => {
    const client = new QueryClient();
    registerAppQueryClient(client);
    client.setQueryData(['cart', 'current'], { owner: 'A' });
    client.setQueryData(['orders', 'detail', '1'], { owner: 'A' });

    clearUserSensitiveQueryCaches(client);

    expect(client.getQueryData(['cart', 'current'])).toBeUndefined();
    expect(client.getQueryData(['orders', 'detail', '1'])).toBeUndefined();
  });
});
