import * as SecureStore from 'expo-secure-store';
import {
  CHECKOUT_RECOVERY_TTL_MS,
  isRecoverableCheckoutSession,
  pendingCheckoutContextStorage,
} from './pendingCheckoutContextStorage';

jest.mock('expo-secure-store', () => ({
  setItemAsync: jest.fn(),
  getItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

const mockSetItemAsync = SecureStore.setItemAsync as jest.Mock;
const mockGetItemAsync = SecureStore.getItemAsync as jest.Mock;
const mockDeleteItemAsync = SecureStore.deleteItemAsync as jest.Mock;

function storedContext(overrides: Record<string, unknown> = {}) {
  return JSON.stringify({
    userId: 'user-a',
    checkoutSessionId: 'sess-1',
    orderId: null,
    paymentTransactionId: null,
    updatedAt: new Date().toISOString(),
    ...overrides,
  });
}

describe('pendingCheckoutContextStorage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('saves checkout context with userId without secrets', async () => {
    await pendingCheckoutContextStorage.save({
      userId: 'user-a',
      checkoutSessionId: 'sess-1',
      orderId: 'ord-1',
      paymentTransactionId: null,
    });

    expect(mockSetItemAsync).toHaveBeenCalledTimes(1);
    const [, raw] = mockSetItemAsync.mock.calls[0];
    const parsed = JSON.parse(raw as string);
    expect(parsed).toMatchObject({
      userId: 'user-a',
      checkoutSessionId: 'sess-1',
      orderId: 'ord-1',
      paymentTransactionId: null,
    });
    expect(raw).not.toMatch(/secret|password|token|checkout_url/i);
  });

  it('same user preserves recovery', async () => {
    mockGetItemAsync.mockResolvedValue(storedContext({ userId: 'user-a' }));
    await expect(
      pendingCheckoutContextStorage.bindToAuthenticatedUser('user-a'),
    ).resolves.toMatchObject({
      userId: 'user-a',
      checkoutSessionId: 'sess-1',
    });
    expect(mockDeleteItemAsync).not.toHaveBeenCalled();
  });

  it('different user clears recovery', async () => {
    mockGetItemAsync.mockResolvedValue(storedContext({ userId: 'user-a' }));
    await expect(
      pendingCheckoutContextStorage.bindToAuthenticatedUser('user-b'),
    ).resolves.toBeNull();
    expect(mockDeleteItemAsync).toHaveBeenCalled();
  });

  it('legacy unbound context does not cross accounts', async () => {
    mockGetItemAsync.mockResolvedValue(storedContext({ userId: null }));
    await expect(
      pendingCheckoutContextStorage.bindToAuthenticatedUser('user-a'),
    ).resolves.toBeNull();
    expect(mockDeleteItemAsync).toHaveBeenCalled();
  });

  it('expired context clears', async () => {
    const stale = new Date(Date.now() - CHECKOUT_RECOVERY_TTL_MS - 1000).toISOString();
    mockGetItemAsync.mockResolvedValue(storedContext({ updatedAt: stale }));
    await expect(pendingCheckoutContextStorage.readValid()).resolves.toBeNull();
    expect(mockDeleteItemAsync).toHaveBeenCalled();
  });

  it('logout path can clear', async () => {
    await pendingCheckoutContextStorage.clear();
    expect(mockDeleteItemAsync).toHaveBeenCalled();
  });

  it('does not save empty session id', async () => {
    await pendingCheckoutContextStorage.save({
      userId: 'user-a',
      checkoutSessionId: '  ',
      orderId: null,
      paymentTransactionId: null,
    });
    expect(mockDeleteItemAsync).toHaveBeenCalled();
    expect(mockSetItemAsync).not.toHaveBeenCalled();
  });
});

describe('isRecoverableCheckoutSession', () => {
  it('rejects expired and completed sessions', () => {
    expect(
      isRecoverableCheckoutSession({ status: 'draft', isExpired: true }),
    ).toBe(false);
    expect(
      isRecoverableCheckoutSession({ status: 'completed', isExpired: false }),
    ).toBe(false);
  });

  it('accepts draft / validated sessions', () => {
    expect(
      isRecoverableCheckoutSession({ status: 'draft', isExpired: false }),
    ).toBe(true);
  });
});
