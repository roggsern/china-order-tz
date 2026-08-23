import {
  ACCOUNT_CAPABILITIES,
  listNativeAccountCapabilities,
  listWebsiteAccountHandoffs,
  resolveAccountCapability,
} from './accountCapabilities';

describe('accountCapabilities', () => {
  it('routes profile/password/notifications/support/close to native APIs', () => {
    for (const id of [
      'profile',
      'addresses',
      'wishlist',
      'security_password',
      'notifications',
      'returns',
      'support',
      'close_account',
    ] as const) {
      const capability = resolveAccountCapability(id);
      expect(capability.decision).toBe('native');
      expect(capability.apiExists).toBe(true);
      expect(capability.nativeHref).toBeTruthy();
    }
  });

  it('hands off settings, privacy, and terms to canonical website paths', () => {
    const handoffs = listWebsiteAccountHandoffs();
    expect(handoffs.map((row) => row.id).sort()).toEqual([
      'privacy',
      'settings',
      'terms',
    ]);
    expect(resolveAccountCapability('privacy').webPath).toBe('/privacy');
    expect(resolveAccountCapability('terms').webPath).toBe('/terms');
    expect(resolveAccountCapability('settings').webPath).toBe('/account');
  });

  it('does not invent notification preference or fake settings native routes', () => {
    const nativeIds = listNativeAccountCapabilities().map((row) => row.id);
    expect(nativeIds).not.toContain('settings');
    expect(nativeIds).not.toContain('privacy');
    expect(nativeIds).not.toContain('terms');
    expect(ACCOUNT_CAPABILITIES.some((row) => row.id === 'settings' && row.decision === 'native')).toBe(
      false,
    );
  });

  it('exposes shared close-account capability without platform forks', () => {
    const close = resolveAccountCapability('close_account');
    expect(close.nativeHref).toBe('/(app)/account/close-account');
    expect(close.decision).toBe('native');
    expect(close.reason).toMatch(/POST \/account\/close/);
  });
});
