import {
  resolvePaymentCheckoutAllowedHosts,
  DEFAULT_NMB_GATEWAY_BASE_URL,
} from '@/src/core/config/env';
import {
  canOpenCheckoutUrl,
  isNmbWebsiteHostedCheckout,
  UNSAFE_CHECKOUT_URL_MESSAGE,
} from './mapPayment';

describe('canOpenCheckoutUrl allowlist', () => {
  it('allows HTTPS Mastercard / NMB gateway hosts', () => {
    expect(
      canOpenCheckoutUrl(
        'https://test-nmbbank.mtf.gateway.mastercard.com/checkout/pay',
      ),
    ).toBe(true);
    expect(
      canOpenCheckoutUrl('https://ap.gateway.mastercard.com/static/checkout'),
    ).toBe(true);
    expect(
      canOpenCheckoutUrl(`${DEFAULT_NMB_GATEWAY_BASE_URL}/static/checkout/checkout.min.js`),
    ).toBe(true);
  });

  it('allows HTTPS hosts under configured Mastercard suffixes', () => {
    expect(
      canOpenCheckoutUrl('https://custom.gateway.mastercard.com/pay'),
    ).toBe(true);
  });

  it('rejects http URLs', () => {
    expect(
      canOpenCheckoutUrl('http://test-nmbbank.mtf.gateway.mastercard.com/pay'),
    ).toBe(false);
  });

  it('rejects unknown hosts', () => {
    expect(canOpenCheckoutUrl('https://evil-domain.com/pay')).toBe(false);
    expect(canOpenCheckoutUrl('https://secure.nmb.example/pay')).toBe(false);
  });

  it('rejects invalid URLs', () => {
    expect(canOpenCheckoutUrl(null)).toBe(false);
    expect(canOpenCheckoutUrl('')).toBe(false);
    expect(canOpenCheckoutUrl('not-a-url')).toBe(false);
    expect(canOpenCheckoutUrl('javascript:alert(1)')).toBe(false);
  });

  it('exposes a safe customer message for unsafe URLs', () => {
    expect(UNSAFE_CHECKOUT_URL_MESSAGE).toBe(
      'Payment service is unavailable. Please try again.',
    );
  });
});

describe('production payment checkout host profile', () => {
  it('includes Mastercard production/sandbox hosts', () => {
    const hosts = resolvePaymentCheckoutAllowedHosts({ isDev: false });
    expect(hosts).toContain('test-nmbbank.mtf.gateway.mastercard.com');
    expect(hosts).toContain('nmbbank.mtf.gateway.mastercard.com');
    expect(hosts).toContain('ap.gateway.mastercard.com');
  });

  it('excludes mock-only checkout.nmb.test from production allowlist', () => {
    const hosts = resolvePaymentCheckoutAllowedHosts({ isDev: false });
    expect(hosts).not.toContain('checkout.nmb.test');
  });

  it('allows mock host only in development profiles', () => {
    const hosts = resolvePaymentCheckoutAllowedHosts({ isDev: true });
    expect(hosts).toContain('checkout.nmb.test');
  });
});

describe('isNmbWebsiteHostedCheckout', () => {
  it('is true when NMB returns session id without redirect checkout_url', () => {
    expect(
      isNmbWebsiteHostedCheckout({
        provider: 'nmb',
        checkoutUrl: null,
        providerReference: 'SESSION000123456789',
      }),
    ).toBe(true);
  });

  it('is false when redirect checkout_url is present', () => {
    expect(
      isNmbWebsiteHostedCheckout({
        provider: 'nmb',
        checkoutUrl: 'https://test-nmbbank.mtf.gateway.mastercard.com/checkout/pay',
        providerReference: 'SESSION000123456789',
      }),
    ).toBe(false);
  });

  it('is false without provider reference', () => {
    expect(
      isNmbWebsiteHostedCheckout({
        provider: 'nmb',
        checkoutUrl: null,
        providerReference: null,
      }),
    ).toBe(false);
  });
});
