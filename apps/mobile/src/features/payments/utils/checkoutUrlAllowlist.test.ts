import {
  NMB_SANDBOX_GATEWAY_HOST,
  resolvePaymentCheckoutAllowedHosts,
} from '@/src/core/config/env';
import {
  canOpenCheckoutUrl,
  isNmbWebsiteHostedCheckout,
  isPaymentCheckoutHostAllowed,
  UNSAFE_CHECKOUT_URL_MESSAGE,
} from './mapPayment';

describe('canOpenCheckoutUrl allowlist', () => {
  it('allows HTTPS Mastercard / NMB production gateway hosts', () => {
    expect(
      canOpenCheckoutUrl('https://nmbbank.mtf.gateway.mastercard.com/checkout/pay'),
    ).toBe(true);
    expect(
      canOpenCheckoutUrl('https://ap.gateway.mastercard.com/static/checkout'),
    ).toBe(true);
  });

  it('allows HTTPS hosts under configured Mastercard suffixes', () => {
    expect(
      canOpenCheckoutUrl('https://custom.gateway.mastercard.com/pay'),
    ).toBe(true);
  });

  it('rejects http URLs', () => {
    expect(
      canOpenCheckoutUrl('http://nmbbank.mtf.gateway.mastercard.com/pay'),
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
  it('excludes sandbox host unless allowSandbox is true', () => {
    const production = resolvePaymentCheckoutAllowedHosts({
      isDev: false,
      allowSandbox: false,
    });
    expect(production).not.toContain(NMB_SANDBOX_GATEWAY_HOST);
    expect(production).toContain('nmbbank.mtf.gateway.mastercard.com');
    expect(production).toContain('ap.gateway.mastercard.com');

    const preview = resolvePaymentCheckoutAllowedHosts({
      isDev: false,
      allowSandbox: true,
    });
    expect(preview).toContain(NMB_SANDBOX_GATEWAY_HOST);
  });

  it('blocks sandbox host in production even when Mastercard suffix would match', () => {
    expect(
      isPaymentCheckoutHostAllowed(NMB_SANDBOX_GATEWAY_HOST, {
        allowSandbox: false,
        allowedHosts: ['nmbbank.mtf.gateway.mastercard.com'],
        allowedSuffixes: ['.mtf.gateway.mastercard.com', '.gateway.mastercard.com'],
      }),
    ).toBe(false);
    expect(
      isPaymentCheckoutHostAllowed(NMB_SANDBOX_GATEWAY_HOST, {
        allowSandbox: true,
        allowedHosts: [],
        allowedSuffixes: ['.mtf.gateway.mastercard.com'],
      }),
    ).toBe(true);
  });

  it('excludes mock-only checkout.nmb.test from production allowlist', () => {
    const hosts = resolvePaymentCheckoutAllowedHosts({
      isDev: false,
      allowSandbox: false,
    });
    expect(hosts).not.toContain('checkout.nmb.test');
  });

  it('allows mock host only in development profiles', () => {
    const hosts = resolvePaymentCheckoutAllowedHosts({
      isDev: true,
      allowSandbox: true,
    });
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
        checkoutUrl: `https://${NMB_SANDBOX_GATEWAY_HOST}/checkout/pay`,
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
