import { DEFAULT_WEB_APP_BASE_URL } from '@/src/core/config/env';
import { buildAccountWebUrl } from './accountWebLinks';

describe('accountWebLinks', () => {
  it('builds storefront account paths from configured web origin', () => {
    expect(buildAccountWebUrl('/account/support')).toBe(
      `${DEFAULT_WEB_APP_BASE_URL}/account/support`,
    );
    expect(buildAccountWebUrl('/account/security')).toMatch(/\/account\/security$/);
  });
});
