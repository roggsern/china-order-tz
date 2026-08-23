/* eslint-disable import/first -- jest.mock must hoist before imports under test */
jest.mock('../api/paymentsApi', () => ({
  retryNmbCheckoutSession: jest.fn(),
}));

jest.mock('./handlePaymentReturn', () => ({
  handleNmbPaymentReturn: jest.fn(),
}));

jest.mock('./nmbBrowser', () => ({
  launchNmbCheckoutForTransaction: jest.fn(),
}));

jest.mock('./recoveryHandoff', () => ({
  handOffCheckoutToPayment: jest.fn().mockResolvedValue(undefined),
}));

import { retryNmbCheckoutSession } from '../api/paymentsApi';
import type { PaymentTransaction } from '../models/types';
import { continueNmbCheckout } from './continueNmbCheckout';
import { handleNmbPaymentReturn } from './handlePaymentReturn';
import { launchNmbCheckoutForTransaction } from './nmbBrowser';

const processingTxn = (): PaymentTransaction => ({
  id: 'txn-1',
  orderId: 'ord-1',
  provider: 'nmb',
  merchantReference: 'COTZ-1',
  currency: 'TZS',
  amount: '1000.00',
  status: 'processing',
  checkoutUrl: null,
  providerReference: 'SESSION000999',
  successIndicator: 'si-1',
  order: null,
  initiatedAt: null,
  completedAt: null,
});

describe('continueNmbCheckout', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('launches the existing NMB transaction without starting another payment', async () => {
    const txn = processingTxn();
    jest.mocked(launchNmbCheckoutForTransaction).mockResolvedValue({
      type: 'dismiss',
      url: null,
      returnParams: {
        resultIndicator: null,
        orderId: null,
        merchantReference: null,
        paymentTransactionId: null,
      },
    });
    jest.mocked(handleNmbPaymentReturn).mockResolvedValue({
      transaction: txn,
      orderId: 'ord-1',
      reconciled: false,
      refreshed: true,
      incomplete: false,
    });

    const result = await continueNmbCheckout({ transaction: txn });

    expect(retryNmbCheckoutSession).not.toHaveBeenCalled();
    expect(launchNmbCheckoutForTransaction).toHaveBeenCalledWith(txn);
    expect(result.transaction.status).toBe('processing');
  });
});
