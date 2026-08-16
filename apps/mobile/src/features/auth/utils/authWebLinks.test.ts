import {
  buildAuthWebUrl,
  resolveAuthWebBaseUrl,
} from './authWebLinks';
import { DEFAULT_WEB_APP_BASE_URL } from '@/src/core/config/env';

describe('authWebLinks', () => {
  it('builds production auth paths from configured web origin', () => {
    expect(
      buildAuthWebUrl('/reset-password', {
        webAppBaseUrl: 'https://chinaordertz.com/',
        isDev: false,
      }),
    ).toBe('https://chinaordertz.com/reset-password');
    expect(
      buildAuthWebUrl('/verify-email', {
        webAppBaseUrl: 'https://chinaordertz.com',
        isDev: false,
      }),
    ).toBe('https://chinaordertz.com/verify-email');
    expect(
      buildAuthWebUrl('/forgot-password', {
        webAppBaseUrl: 'https://chinaordertz.com',
        isDev: false,
      }),
    ).toBe('https://chinaordertz.com/forgot-password');
  });

  it('rejects loopback web bases in production-like builds', () => {
    expect(
      resolveAuthWebBaseUrl({
        webAppBaseUrl: 'http://localhost:3000',
        isDev: false,
      }),
    ).toBe(DEFAULT_WEB_APP_BASE_URL);
    expect(
      resolveAuthWebBaseUrl({
        webAppBaseUrl: 'http://10.0.2.2:3000',
        isDev: false,
      }),
    ).toBe(DEFAULT_WEB_APP_BASE_URL);
  });

  it('allows loopback only in development', () => {
    expect(
      resolveAuthWebBaseUrl({
        webAppBaseUrl: 'http://localhost:3000',
        isDev: true,
      }),
    ).toBe('http://localhost:3000');
  });
});
