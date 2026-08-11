import * as WebBrowser from 'expo-web-browser';
import { DEFAULT_WEB_APP_BASE_URL } from '@/src/core/config/env';
import type { PaymentTransaction } from '../models/types';
import { canOpenCheckoutUrl, extractNmbReturnParams } from './mapPayment';
import {
  buildNmbWebHostedCheckoutLauncherUrl,
  buildPaymentReturnRedirectUrl,
  canOpenNmbWebLauncherUrl,
  launchNmbCheckoutForTransaction,
  openNmbHostedCheckout,
  openNmbWebsiteHostedCheckout,
} from './nmbBrowser';

jest.mock('expo-linking', () => ({
  createURL: (path: string, options?: { scheme?: string }) =>
    `${options?.scheme ?? 'chinaordertz'}://${path}`,
}));

jest.mock('expo-web-browser', () => ({
  openAuthSessionAsync: jest.fn(),
}));

const processingTxn = (
  overrides: Partial<PaymentTransaction> = {},
): PaymentTransaction => ({
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
  ...overrides,
});

describe('NMB browser helpers', () => {
  beforeEach(() => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockReset();
  });

  it('builds app redirect URL for auth session return', () => {
    expect(buildPaymentReturnRedirectUrl()).toContain('payment-return');
  });

  it('builds the web NMB launcher URL with session + mobileReturn', () => {
    const url = buildNmbWebHostedCheckoutLauncherUrl({
      paymentTransactionId: 'txn-abc',
      sessionId: 'SESSION000999',
      successIndicator: 'si-1',
      webAppBaseUrl: DEFAULT_WEB_APP_BASE_URL,
    });
    expect(url).toBe(
      `${DEFAULT_WEB_APP_BASE_URL}/payments/txn-abc/nmb?sessionId=SESSION000999&successIndicator=si-1&mobileReturn=1`,
    );
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

  it('allows only the configured web app host for the HC launcher', () => {
    expect(
      canOpenNmbWebLauncherUrl(
        `${DEFAULT_WEB_APP_BASE_URL}/payments/txn-1/nmb?mobileReturn=1`,
      ),
    ).toBe(true);
    expect(
      canOpenNmbWebLauncherUrl('https://www.chinaordertz.com/payments/txn-1/nmb'),
    ).toBe(true);
    expect(canOpenNmbWebLauncherUrl('https://evil-domain.com/payments/x/nmb')).toBe(
      false,
    );
    expect(
      canOpenNmbWebLauncherUrl('http://chinaordertz.com/payments/txn-1/nmb'),
    ).toBe(false);
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

  it('opens Website Hosted Checkout in the system browser via web launcher', async () => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
      type: 'dismiss',
    } as WebBrowser.WebBrowserAuthSessionResult);

    const txn = processingTxn();
    const result = await launchNmbCheckoutForTransaction(txn);

    expect(WebBrowser.openAuthSessionAsync).toHaveBeenCalledTimes(1);
    const [openedUrl, redirectUrl] = jest.mocked(WebBrowser.openAuthSessionAsync).mock
      .calls[0];
    expect(openedUrl).toBe(
      buildNmbWebHostedCheckoutLauncherUrl({
        paymentTransactionId: txn.id,
        sessionId: txn.providerReference,
        successIndicator: txn.successIndicator,
      }),
    );
    expect(String(openedUrl)).toContain('/payments/txn-1/nmb');
    expect(String(openedUrl)).toContain('mobileReturn=1');
    expect(String(redirectUrl)).toContain('payment-return');
    expect(result.type).toBe('dismiss');
    expect(result.url).toBeNull();
  });

  it('does not invent paid status when the browser dismisses', async () => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
      type: 'dismiss',
    } as WebBrowser.WebBrowserAuthSessionResult);

    const result = await openNmbWebsiteHostedCheckout({
      paymentTransactionId: 'txn-1',
      sessionId: 'SESSION000999',
      successIndicator: 'si-1',
    });

    expect(result.type).toBe('dismiss');
    expect(result.returnParams.resultIndicator).toBeNull();
  });

  it('maps deep-link success return without marking paid locally', async () => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
      type: 'success',
      url: 'chinaordertz://payment-return?resultIndicator=ri-1&paymentTransactionId=txn-1',
    });

    const result = await launchNmbCheckoutForTransaction(processingTxn());
    expect(result.type).toBe('success');
    expect(result.returnParams.resultIndicator).toBe('ri-1');
    expect(result.returnParams.paymentTransactionId).toBe('txn-1');
  });

  it('keeps Processing path when browser launch fails', async () => {
    jest
      .mocked(WebBrowser.openAuthSessionAsync)
      .mockRejectedValue(new Error('Unable to open browser'));

    await expect(launchNmbCheckoutForTransaction(processingTxn())).rejects.toThrow(
      'Unable to open browser',
    );
  });

  it('still opens redirect checkout_url via AuthSession when present', async () => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
      type: 'cancel',
    } as WebBrowser.WebBrowserAuthSessionResult);
    const checkoutUrl =
      'https://test-nmbbank.mtf.gateway.mastercard.com/checkout/pay';

    await launchNmbCheckoutForTransaction(
      processingTxn({ checkoutUrl, providerReference: 'SESSION000999' }),
    );

    expect(WebBrowser.openAuthSessionAsync).toHaveBeenCalledWith(
      checkoutUrl,
      expect.stringContaining('payment-return'),
    );
  });
});
