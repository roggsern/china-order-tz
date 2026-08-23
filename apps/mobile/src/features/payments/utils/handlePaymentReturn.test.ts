import type { PendingPaymentContext } from '../storage/pendingPaymentContextStorage';
import type { PaymentTransaction } from '../models/types';
import { handleNmbPaymentReturn, handlePaymentReturn } from './handlePaymentReturn';

const mockResolvePaymentReturnContext = jest.fn();
const mockReconcileNmbBrowserReturn = jest.fn();
const mockRefreshPaymentTransaction = jest.fn();

jest.mock('../api/paymentsApi', () => ({
  resolvePaymentReturnContext: (...args: unknown[]) =>
    mockResolvePaymentReturnContext(...args),
  reconcileNmbBrowserReturn: (...args: unknown[]) =>
    mockReconcileNmbBrowserReturn(...args),
  refreshPaymentTransaction: (...args: unknown[]) =>
    mockRefreshPaymentTransaction(...args),
}));

const baseTxn = (
  overrides: Partial<PaymentTransaction> = {},
): PaymentTransaction => ({
  id: 'txn-1',
  orderId: 'ord-1',
  provider: 'nmb',
  providerReference: null,
  merchantReference: 'COTZ-PAY-1',
  currency: 'TZS',
  amount: '25000.00',
  status: 'processing',
  checkoutUrl: 'https://checkout.nmb.test/pay',
  successIndicator: 'si-1',
  order: {
    id: 'ord-1',
    orderNumber: 'COTZ-100',
    status: 'pending_payment',
    grandTotal: '25000.00',
    currency: 'TZS',
  },
  initiatedAt: null,
  completedAt: null,
  ...overrides,
});

describe('handleNmbPaymentReturn', () => {
  const storageState: { value: PendingPaymentContext | null } = { value: null };

  const storage = {
    save: jest.fn(async (ctx: Omit<PendingPaymentContext, 'updatedAt'>) => {
      storageState.value = {
        ...ctx,
        updatedAt: '2026-08-10T00:00:00Z',
      } as PendingPaymentContext;
    }),
    merge: jest.fn(async (partial: Partial<PendingPaymentContext>) => {
      const existing = storageState.value;
      storageState.value = {
        userId:
          partial.userId !== undefined ? partial.userId : existing?.userId ?? null,
        orderId: partial.orderId !== undefined ? partial.orderId : existing?.orderId ?? null,
        paymentTransactionId:
          partial.paymentTransactionId !== undefined
            ? partial.paymentTransactionId
            : existing?.paymentTransactionId ?? null,
        merchantReference:
          partial.merchantReference !== undefined
            ? partial.merchantReference
            : existing?.merchantReference ?? null,
        successIndicator:
          partial.successIndicator !== undefined
            ? partial.successIndicator
            : existing?.successIndicator ?? null,
        resultIndicator:
          partial.resultIndicator !== undefined
            ? partial.resultIndicator
            : existing?.resultIndicator ?? null,
        checkoutSessionId:
          partial.checkoutSessionId !== undefined
            ? partial.checkoutSessionId
            : existing?.checkoutSessionId ?? null,
        updatedAt: '2026-08-10T00:00:00Z',
      };
      return storageState.value;
    }),
    read: jest.fn(async () => storageState.value),
    readValid: jest.fn(async () => storageState.value),
    clear: jest.fn(async () => {
      storageState.value = null;
    }),
  };

  beforeEach(() => {
    storageState.value = null;
    mockResolvePaymentReturnContext.mockReset();
    mockReconcileNmbBrowserReturn.mockReset();
    mockRefreshPaymentTransaction.mockReset();
    storage.save.mockClear();
    storage.merge.mockClear();
    storage.read.mockClear();
    storage.readValid.mockClear();
    storage.clear.mockClear();
  });

  it('cold start: uses persisted context + URL resultIndicator, reconciles, then refreshes', async () => {
    storageState.value = {
      userId: 'user-a',
      orderId: 'ord-1',
      paymentTransactionId: 'txn-1',
      merchantReference: 'COTZ-PAY-1',
      successIndicator: 'si-1',
      resultIndicator: null,
      checkoutSessionId: 'sess-1',
      updatedAt: '2026-08-10T00:00:00Z',
    };

    mockReconcileNmbBrowserReturn.mockResolvedValue(
      baseTxn({ status: 'processing' }),
    );
    mockRefreshPaymentTransaction.mockResolvedValue(
      baseTxn({ status: 'successful' }),
    );

    const result = await handleNmbPaymentReturn({
      returnUrl: 'chinaordertz://payment-return?resultIndicator=ri-cold',
      storage: storage as never,
    });

    expect(mockReconcileNmbBrowserReturn).toHaveBeenCalledWith(
      expect.objectContaining({
        paymentTransactionId: 'txn-1',
        merchantReference: 'COTZ-PAY-1',
        successIndicator: 'si-1',
        resultIndicator: 'ri-cold',
        orderId: 'ord-1',
      }),
    );
    expect(mockRefreshPaymentTransaction).toHaveBeenCalledWith('txn-1');
    expect(result.reconciled).toBe(true);
    expect(result.refreshed).toBe(true);
    expect(result.transaction?.status).toBe('successful');
    expect(storage.clear).toHaveBeenCalled();
  });

  it('warm return: reconciles when resultIndicator + persisted proof are present', async () => {
    storageState.value = {
      userId: 'user-a',
      orderId: 'ord-9',
      paymentTransactionId: 'txn-9',
      merchantReference: 'COTZ-PAY-9',
      successIndicator: 'si-9',
      resultIndicator: null,
      checkoutSessionId: null,
      updatedAt: '2026-08-10T00:00:00Z',
    };

    mockReconcileNmbBrowserReturn.mockResolvedValue(
      baseTxn({ id: 'txn-9', orderId: 'ord-9', status: 'processing' }),
    );
    mockRefreshPaymentTransaction.mockResolvedValue(
      baseTxn({ id: 'txn-9', orderId: 'ord-9', status: 'successful' }),
    );

    const result = await handleNmbPaymentReturn({
      resultIndicator: 'ri-warm',
      orderId: 'ord-9',
      paymentTransactionId: 'txn-9',
      merchantReference: 'COTZ-PAY-9',
      storage: storage as never,
    });

    expect(mockReconcileNmbBrowserReturn).toHaveBeenCalled();
    expect(result.reconciled).toBe(true);
    expect(result.orderId).toBe('ord-9');
  });

  it('calls return-context when transaction id is missing, then refresh', async () => {
    storageState.value = {
      userId: 'user-a',
      orderId: 'ord-2',
      paymentTransactionId: null,
      merchantReference: 'COTZ-PAY-2',
      successIndicator: null,
      resultIndicator: null,
      checkoutSessionId: null,
      updatedAt: '2026-08-10T00:00:00Z',
    };

    mockResolvePaymentReturnContext.mockResolvedValue(
      baseTxn({
        id: 'txn-2',
        orderId: 'ord-2',
        merchantReference: 'COTZ-PAY-2',
        successIndicator: 'si-2',
      }),
    );
    mockReconcileNmbBrowserReturn.mockResolvedValue(
      baseTxn({ id: 'txn-2', orderId: 'ord-2' }),
    );
    mockRefreshPaymentTransaction.mockResolvedValue(
      baseTxn({ id: 'txn-2', orderId: 'ord-2', status: 'successful' }),
    );

    const result = await handleNmbPaymentReturn({
      resultIndicator: 'ri-2',
      storage: storage as never,
    });

    expect(mockResolvePaymentReturnContext).toHaveBeenCalledWith({
      orderId: 'ord-2',
      merchantReference: 'COTZ-PAY-2',
    });
    expect(mockReconcileNmbBrowserReturn).toHaveBeenCalled();
    expect(result.refreshed).toBe(true);
  });

  it('does not invent success when reconcile fails — refresh remains source of truth', async () => {
    storageState.value = {
      userId: 'user-a',
      orderId: 'ord-1',
      paymentTransactionId: 'txn-1',
      merchantReference: 'COTZ-PAY-1',
      successIndicator: 'si-1',
      resultIndicator: null,
      checkoutSessionId: null,
      updatedAt: '2026-08-10T00:00:00Z',
    };

    mockReconcileNmbBrowserReturn.mockRejectedValue(new Error('proof failed'));
    mockRefreshPaymentTransaction.mockResolvedValue(
      baseTxn({ status: 'processing' }),
    );

    const result = await handleNmbPaymentReturn({
      resultIndicator: 'ri-bad',
      storage: storage as never,
    });

    expect(result.reconciled).toBe(false);
    expect(result.refreshed).toBe(true);
    expect(result.transaction?.status).toBe('processing');
    expect(storage.clear).not.toHaveBeenCalled();
  });

  it('ignores expired storage via readValid returning null', async () => {
    storage.readValid.mockResolvedValueOnce(null);
    mockRefreshPaymentTransaction.mockResolvedValue(baseTxn());

    const result = await handleNmbPaymentReturn({
      paymentTransactionId: 'txn-1',
      storage: storage as never,
    });

    expect(result.refreshed).toBe(true);
    expect(storage.readValid).toHaveBeenCalled();
  });

  it('does not mark paid from the return URL itself', async () => {
    mockRefreshPaymentTransaction.mockResolvedValue(
      baseTxn({ status: 'processing' }),
    );

    const result = await handlePaymentReturn({
      returnUrl:
        'chinaordertz://payment-return?resultIndicator=ri-1&paymentTransactionId=txn-1&order_id=ord-1',
      paymentTransactionId: 'txn-1',
      storage: storage as never,
    });

    expect(result.transaction?.status).toBe('processing');
    expect(result.refreshed).toBe(true);
  });

  it('does not apply NMB reconcile to a Snippe-style return without resultIndicator', async () => {
    storageState.value = {
      userId: 'user-a',
      orderId: 'ord-snippe',
      paymentTransactionId: 'txn-snippe-1',
      merchantReference: null,
      successIndicator: null,
      resultIndicator: null,
      checkoutSessionId: null,
      updatedAt: '2026-08-10T00:00:00Z',
    };
    mockRefreshPaymentTransaction.mockResolvedValue(
      baseTxn({
        id: 'txn-snippe-1',
        orderId: 'ord-snippe',
        provider: 'snippe',
        status: 'processing',
      }),
    );

    const result = await handlePaymentReturn({
      returnUrl:
        'chinaordertz://payment-return?order_id=ord-snippe&paymentTransactionId=txn-snippe-1',
      storage: storage as never,
    });

    expect(mockReconcileNmbBrowserReturn).not.toHaveBeenCalled();
    expect(mockRefreshPaymentTransaction).toHaveBeenCalledWith('txn-snippe-1');
    expect(result.reconciled).toBe(false);
    expect(result.refreshed).toBe(true);
    expect(result.transaction?.provider).toBe('snippe');
  });
});
