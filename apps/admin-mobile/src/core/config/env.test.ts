import {
  isLoopbackApiUrl,
  MISSING_PRODUCTION_API_URL_MESSAGE,
  resolveApiBaseUrl,
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
