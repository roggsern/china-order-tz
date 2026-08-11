import * as SecureStore from 'expo-secure-store';
import {
  PAYMENT_RECOVERY_TTL_MS,
  pendingPaymentContextStorage,
} from './pendingPaymentContextStorage';

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
    orderId: 'ord-1',
    paymentTransactionId: 'txn-1',
    merchantReference: 'COTZ-PAY-1',
    successIndicator: 'si-1',
    resultIndicator: 'ri-1',
    checkoutSessionId: null,
    updatedAt: new Date().toISOString(),
    ...overrides,
  });
}

describe('pendingPaymentContextStorage', () => {
  beforeEach(() => {
    mockSetItemAsync.mockReset();
    mockGetItemAsync.mockReset();
    mockDeleteItemAsync.mockReset();
  });

  it('valid context restores with userId', async () => {
    await pendingPaymentContextStorage.save({
      userId: 'user-a',
      orderId: 'ord-1',
      paymentTransactionId: 'txn-1',
      merchantReference: 'COTZ-PAY-1',
      successIndicator: 'si-1',
      resultIndicator: 'ri-1',
      checkoutSessionId: 'sess-1',
    });

    expect(mockSetItemAsync).toHaveBeenCalledWith(
      'cotz.payment.pending_context',
      expect.stringContaining('"userId":"user-a"'),
    );

    mockGetItemAsync.mockResolvedValueOnce(storedContext());

    await expect(pendingPaymentContextStorage.readValid()).resolves.toMatchObject({
      userId: 'user-a',
      orderId: 'ord-1',
      successIndicator: 'si-1',
    });
  });

  it('expired context is discarded and cleared (TTL)', async () => {
    const stale = new Date(Date.now() - PAYMENT_RECOVERY_TTL_MS - 1000).toISOString();
    mockGetItemAsync.mockResolvedValue(
      storedContext({ updatedAt: stale }),
    );

    await expect(pendingPaymentContextStorage.readValid()).resolves.toBeNull();
    expect(mockDeleteItemAsync).toHaveBeenCalled();
  });

  it('confirmed success path can clear context', async () => {
    await pendingPaymentContextStorage.clear();
    expect(mockDeleteItemAsync).toHaveBeenCalled();
  });

  it('clears empty context instead of saving blanks', async () => {
    await pendingPaymentContextStorage.save({
      userId: 'user-a',
      orderId: null,
      paymentTransactionId: null,
      merchantReference: null,
      successIndicator: null,
      resultIndicator: null,
      checkoutSessionId: null,
    });
    expect(mockDeleteItemAsync).toHaveBeenCalled();
    expect(mockSetItemAsync).not.toHaveBeenCalled();
  });

  it('same user re-login preserves valid payment recovery', async () => {
    mockGetItemAsync.mockResolvedValue(storedContext({ userId: 'user-a' }));

    await expect(
      pendingPaymentContextStorage.bindToAuthenticatedUser('user-a'),
    ).resolves.toMatchObject({
      userId: 'user-a',
      orderId: 'ord-1',
    });
    expect(mockDeleteItemAsync).not.toHaveBeenCalled();
  });

  it('different user login clears old context', async () => {
    mockGetItemAsync.mockResolvedValue(storedContext({ userId: 'user-a' }));

    await expect(
      pendingPaymentContextStorage.bindToAuthenticatedUser('user-b'),
    ).resolves.toBeNull();
    expect(mockDeleteItemAsync).toHaveBeenCalled();
  });

  it('legacy unbound context does not cross accounts', async () => {
    mockGetItemAsync.mockResolvedValue(storedContext({ userId: null }));

    await expect(
      pendingPaymentContextStorage.bindToAuthenticatedUser('user-a'),
    ).resolves.toBeNull();
    expect(mockDeleteItemAsync).toHaveBeenCalled();
    expect(mockSetItemAsync).not.toHaveBeenCalled();
  });
});
