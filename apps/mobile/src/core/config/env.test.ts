import {
  DEFAULT_WEB_APP_BASE_URL,
  isLoopbackApiUrl,
  MISSING_PRODUCTION_API_URL_MESSAGE,
  NMB_SANDBOX_GATEWAY_HOST,
  resolveAllowNmbSandboxCheckout,
  resolveApiBaseUrl,
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

describe('resolveAllowNmbSandboxCheckout', () => {
  it('is true in development without explicit flag', () => {
    expect(
      resolveAllowNmbSandboxCheckout({
        fromProcess: undefined,
        fromExtra: undefined,
        isDev: true,
      }),
    ).toBe(true);
  });

  it('is false in production without explicit opt-in', () => {
    expect(
      resolveAllowNmbSandboxCheckout({
        fromProcess: undefined,
        fromExtra: undefined,
        isDev: false,
      }),
    ).toBe(false);
  });

  it('allows explicit preview opt-in in non-dev builds', () => {
    expect(
      resolveAllowNmbSandboxCheckout({
        fromProcess: 'true',
        isDev: false,
      }),
    ).toBe(true);
  });

  it('honors explicit false even if extra says true', () => {
    expect(
      resolveAllowNmbSandboxCheckout({
        fromProcess: 'false',
        fromExtra: 'true',
        isDev: false,
      }),
    ).toBe(false);
  });
});

describe('resolvePaymentCheckoutAllowedHosts', () => {
  it('excludes sandbox and mock hosts from production release allowlist', () => {
    const production = resolvePaymentCheckoutAllowedHosts({
      isDev: false,
      allowSandbox: false,
    });
    expect(production).not.toContain(NMB_SANDBOX_GATEWAY_HOST);
    expect(production).not.toContain('checkout.nmb.test');
    expect(production).toContain('nmbbank.mtf.gateway.mastercard.com');
    expect(production).toContain('ap.gateway.mastercard.com');
  });

  it('includes sandbox host when preview opt-in is enabled', () => {
    const preview = resolvePaymentCheckoutAllowedHosts({
      isDev: false,
      allowSandbox: true,
    });
    expect(preview).toContain(NMB_SANDBOX_GATEWAY_HOST);
    expect(preview).not.toContain('checkout.nmb.test');
  });

  it('allows mock host only in development profiles', () => {
    const hosts = resolvePaymentCheckoutAllowedHosts({
      isDev: true,
      allowSandbox: true,
    });
    expect(hosts).toContain('checkout.nmb.test');
    expect(hosts).toContain(NMB_SANDBOX_GATEWAY_HOST);
  });
});
