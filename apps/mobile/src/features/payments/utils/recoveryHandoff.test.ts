import { useAuthStore } from '@/src/core/auth/authStore';
import { pendingCheckoutContextStorage } from '@/src/features/checkout/storage/pendingCheckoutContextStorage';
import { pendingPaymentContextStorage } from '../storage/pendingPaymentContextStorage';
import {
  clearPaymentAndCheckoutContexts,
  handOffCheckoutToPayment,
} from './recoveryHandoff';

jest.mock('../storage/pendingPaymentContextStorage', () => ({
  pendingPaymentContextStorage: {
    save: jest.fn(),
    clear: jest.fn(),
  },
}));

jest.mock('@/src/features/checkout/storage/pendingCheckoutContextStorage', () => ({
  pendingCheckoutContextStorage: {
    clear: jest.fn(),
  },
}));

describe('recoveryHandoff', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    useAuthStore.setState({
      status: 'authenticated',
      user: {
        id: 'user-a',
        name: 'Ada',
        email: 'ada@example.com',
      },
      bootstrapStatus: 'complete',
    });
  });

  it('checkout recovery exists before payment; payment start moves ownership', async () => {
    await handOffCheckoutToPayment({
      orderId: 'ord-1',
      paymentTransactionId: 'txn-1',
      merchantReference: 'ref',
      successIndicator: 'si',
      checkoutSessionId: 'sess-1',
    });

    expect(pendingPaymentContextStorage.save).toHaveBeenCalledWith(
      expect.objectContaining({
        userId: 'user-a',
        orderId: 'ord-1',
        paymentTransactionId: 'txn-1',
        checkoutSessionId: 'sess-1',
      }),
    );
    expect(pendingCheckoutContextStorage.clear).toHaveBeenCalled();
  });

  it('payment success clears obsolete contexts', async () => {
    await clearPaymentAndCheckoutContexts();
    expect(pendingPaymentContextStorage.clear).toHaveBeenCalled();
    expect(pendingCheckoutContextStorage.clear).toHaveBeenCalled();
  });
});
