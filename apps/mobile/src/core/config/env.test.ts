import {
  DEFAULT_NMB_GATEWAY_BASE_URL,
  DEFAULT_WEB_APP_BASE_URL,
  isLoopbackApiUrl,
  MISSING_PRODUCTION_API_URL_MESSAGE,
  resolveApiBaseUrl,
  resolveNmbGatewayBaseUrl,
  resolvePaymentCheckoutAllowedHosts,
  resolveWebAppBaseUrl,
} from './env';

describe('resolveApiBaseUrl', () => {
  it('allows localhost fallback in development', () => {
    expect(
      resolveApiBaseUrl({
        fromProcess: undefined,
        fromExtra: undefined,
        isDev: true,
      }),
    ).toBe('http://localhost:8000/api/v1');
  });

  it('uses explicit process URL in development', () => {
    expect(
      resolveApiBaseUrl({
        fromProcess: 'https://api.chinaordertz.com/api/v1/',
        isDev: true,
      }),
    ).toBe('https://api.chinaordertz.com/api/v1');
  });

  it('fails fast in production when API URL missing', () => {
    expect(() =>
      resolveApiBaseUrl({
        fromProcess: undefined,
        fromExtra: undefined,
        isDev: false,
      }),
    ).toThrow(MISSING_PRODUCTION_API_URL_MESSAGE);
  });

  it('fails fast in production when API URL is localhost', () => {
    expect(() =>
      resolveApiBaseUrl({
        fromProcess: 'http://localhost:8000/api/v1',
        isDev: false,
      }),
    ).toThrow(MISSING_PRODUCTION_API_URL_MESSAGE);
  });

  it('accepts production API URL', () => {
    expect(
      resolveApiBaseUrl({
        fromExtra: 'https://api.chinaordertz.com/api/v1',
        isDev: false,
      }),
    ).toBe('https://api.chinaordertz.com/api/v1');
  });
});

describe('isLoopbackApiUrl', () => {
  it('detects loopback hosts', () => {
    expect(isLoopbackApiUrl('http://localhost:8000/api/v1')).toBe(true);
    expect(isLoopbackApiUrl('http://10.0.2.2:8000/api/v1')).toBe(true);
    expect(isLoopbackApiUrl('https://api.chinaordertz.com/api/v1')).toBe(false);
  });
});

describe('resolveWebAppBaseUrl', () => {
  it('defaults to chinaordertz.com storefront origin', () => {
    expect(
      resolveWebAppBaseUrl({ fromProcess: undefined, fromExtra: undefined }),
    ).toBe(DEFAULT_WEB_APP_BASE_URL);
  });

  it('prefers EXPO_PUBLIC_WEB_APP_BASE_URL when set', () => {
    expect(
      resolveWebAppBaseUrl({
        fromProcess: 'https://www.chinaordertz.com/',
        fromExtra: 'https://chinaordertz.com',
      }),
    ).toBe('https://www.chinaordertz.com');
  });
});

describe('resolveNmbGatewayBaseUrl', () => {
  it('defaults to the NMB Mastercard sandbox gateway', () => {
    expect(
      resolveNmbGatewayBaseUrl({ fromProcess: undefined, fromExtra: undefined }),
    ).toBe(DEFAULT_NMB_GATEWAY_BASE_URL);
  });

  it('prefers EXPO_PUBLIC_NMB_GATEWAY_URL when set', () => {
    expect(
      resolveNmbGatewayBaseUrl({
        fromProcess: 'https://nmbbank.mtf.gateway.mastercard.com/',
        fromExtra: 'https://test-nmbbank.mtf.gateway.mastercard.com',
      }),
    ).toBe('https://nmbbank.mtf.gateway.mastercard.com');
  });
});

describe('resolvePaymentCheckoutAllowedHosts', () => {
  it('keeps HTTPS Mastercard hosts and strips mock host outside development', () => {
    const production = resolvePaymentCheckoutAllowedHosts({ isDev: false });
    expect(production).toContain('test-nmbbank.mtf.gateway.mastercard.com');
    expect(production).not.toContain('checkout.nmb.test');
  });
});
