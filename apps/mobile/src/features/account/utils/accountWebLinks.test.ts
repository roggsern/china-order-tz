import { DEFAULT_WEB_APP_BASE_URL } from '@/src/core/config/env';
import { buildAccountWebUrl } from './accountWebLinks';

describe('accountWebLinks', () => {
  it('builds storefront account path from configured web origin', () => {
    expect(buildAccountWebUrl('/account')).toBe(
      `${DEFAULT_WEB_APP_BASE_URL}/account`,
    );
  });

  it('builds canonical privacy and terms URLs for shared Android/iOS handoff', () => {
    expect(buildAccountWebUrl('/privacy')).toBe(
      `${DEFAULT_WEB_APP_BASE_URL}/privacy`,
    );
    expect(buildAccountWebUrl('/terms')).toBe(
      `${DEFAULT_WEB_APP_BASE_URL}/terms`,
    );
  });
});
