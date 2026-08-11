/* eslint-disable import/first -- jest.mock must hoist before imports under test */
jest.mock('../api/paymentsApi', () => ({
  createOrderFromCheckoutSession: jest.fn(),
  startPayment: jest.fn(),
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

import { startPayment } from '../api/paymentsApi';
import type { PaymentTransaction } from '../models/types';
import { handleNmbPaymentReturn } from './handlePaymentReturn';
import { canOpenCheckoutUrl, isNmbWebsiteHostedCheckout } from './mapPayment';
import { launchNmbCheckoutForTransaction } from './nmbBrowser';
import { payOrderWithNmb } from './payWithNmb';

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

describe('payOrderWithNmb', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('reconciles after deep-link return and never invents paid locally', async () => {
    const started = processingTxn();
    jest.mocked(startPayment).mockResolvedValue(started);
    jest.mocked(launchNmbCheckoutForTransaction).mockResolvedValue({
      type: 'success',
      url: 'chinaordertz://payment-return?resultIndicator=ri-1',
      returnParams: {
        resultIndicator: 'ri-1',
        orderId: null,
        merchantReference: null,
        paymentTransactionId: null,
      },
    });
    jest.mocked(handleNmbPaymentReturn).mockResolvedValue({
      transaction: { ...started, status: 'successful' },
      orderId: 'ord-1',
      reconciled: true,
      refreshed: true,
      incomplete: false,
    });

    const result = await payOrderWithNmb({ orderId: 'ord-1' });

    expect(launchNmbCheckoutForTransaction).toHaveBeenCalledWith(started);
    expect(handleNmbPaymentReturn).toHaveBeenCalledWith(
      expect.objectContaining({
        returnUrl: 'chinaordertz://payment-return?resultIndicator=ri-1',
        resultIndicator: 'ri-1',
        paymentTransactionId: 'txn-1',
      }),
    );
    expect(result.transaction.status).toBe('successful');
    expect(result.browserType).toBe('success');
  });

  it('refreshes server status after browser dismiss; leaves Processing when unpaid', async () => {
    const started = processingTxn();
    jest.mocked(startPayment).mockResolvedValue(started);
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
      transaction: started,
      orderId: 'ord-1',
      reconciled: false,
      refreshed: true,
      incomplete: false,
    });

    const result = await payOrderWithNmb({ orderId: 'ord-1' });

    expect(handleNmbPaymentReturn).toHaveBeenCalledWith(
      expect.objectContaining({
        paymentTransactionId: 'txn-1',
        orderId: 'ord-1',
      }),
    );
    expect(result.transaction.status).toBe('processing');
  });

  it('does not mark paid when browser launch fails; start txn stays Processing', async () => {
    const started = processingTxn();
    jest.mocked(startPayment).mockResolvedValue(started);
    jest
      .mocked(launchNmbCheckoutForTransaction)
      .mockRejectedValue(new Error('Unable to open browser'));

    await expect(payOrderWithNmb({ orderId: 'ord-1' })).rejects.toThrow(
      'Unable to open browser',
    );
    expect(handleNmbPaymentReturn).not.toHaveBeenCalled();
    expect(started.status).toBe('processing');
  });
});

describe('Website HC eligibility helpers used by pay flow', () => {
  it('treats session-without-url as Website Hosted Checkout', () => {
    expect(
      isNmbWebsiteHostedCheckout({
        provider: 'nmb',
        checkoutUrl: null,
        providerReference: 'SESSION000999',
      }),
    ).toBe(true);
    expect(canOpenCheckoutUrl(null)).toBe(false);
  });
});
