import * as WebBrowser from 'expo-web-browser';
import {
  buildPaymentReturnRedirectUrl,
  launchNmbCheckoutForTransaction,
  openNmbHostedCheckout,
} from './nmbBrowser';
import { canOpenCheckoutUrl, extractNmbReturnParams } from './mapPayment';
import { useNmbWebsiteCheckoutStore } from '../state/nmbWebsiteCheckoutStore';
import type { PaymentTransaction } from '../models/types';

jest.mock('expo-linking', () => ({
  createURL: (path: string, options?: { scheme?: string }) =>
    `${options?.scheme ?? 'chinaordertz'}://${path}`,
}));

jest.mock('expo-web-browser', () => ({
  openAuthSessionAsync: jest.fn(),
}));

describe('NMB browser helpers', () => {
  beforeEach(() => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockReset();
    useNmbWebsiteCheckoutStore.setState({ request: null, resolve: null });
  });

  it('builds app redirect URL for auth session return', () => {
    expect(buildPaymentReturnRedirectUrl()).toContain('payment-return');
  });

  it('only opens allowlisted https checkout URLs from the server', () => {
    expect(
      canOpenCheckoutUrl(
        'https://test-nmbbank.mtf.gateway.mastercard.com/checkout',
      ),
    ).toBe(true);
    expect(canOpenCheckoutUrl('https://evil-domain.com/pay')).toBe(false);
    expect(
      canOpenCheckoutUrl('http://test-nmbbank.mtf.gateway.mastercard.com/pay'),
    ).toBe(false);
    expect(canOpenCheckoutUrl('javascript:alert(1)')).toBe(false);
  });

  it('parses browser return query for reconciliation proof only', () => {
    const params = extractNmbReturnParams(
      'chinaordertz://payment-return?resultIndicator=abc',
    );
    expect(params.resultIndicator).toBe('abc');
  });

  it('rejects non-allowlisted redirect URLs before opening the browser', async () => {
    await expect(openNmbHostedCheckout('https://evil-domain.com/pay')).rejects.toThrow(
      'Payment service is unavailable. Please try again.',
    );
    expect(WebBrowser.openAuthSessionAsync).not.toHaveBeenCalled();
  });

  it('launches Website Hosted Checkout when NMB returns session without checkout_url', async () => {
    const txn: PaymentTransaction = {
      id: 'txn-1',
      orderId: 'ord-1',
      provider: 'nmb',
      merchantReference: 'COTZ-1',
      currency: 'TZS',
      amount: '25000.00',
      status: 'processing',
      checkoutUrl: null,
      providerReference: 'SESSION000999',
      successIndicator: 'si-1',
      order: null,
      initiatedAt: null,
      completedAt: null,
    };

    const pending = launchNmbCheckoutForTransaction(txn);
    await Promise.resolve();
    expect(useNmbWebsiteCheckoutStore.getState().request?.sessionId).toBe(
      'SESSION000999',
    );

    useNmbWebsiteCheckoutStore.getState().complete({
      type: 'cancel',
      url: null,
      returnParams: {
        resultIndicator: null,
        orderId: null,
        merchantReference: null,
        paymentTransactionId: null,
      },
    });

    await expect(pending).resolves.toMatchObject({ type: 'cancel' });
    expect(WebBrowser.openAuthSessionAsync).not.toHaveBeenCalled();
  });
});
