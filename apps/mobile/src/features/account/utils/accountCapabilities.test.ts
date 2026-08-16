import {
  ACCOUNT_CAPABILITIES,
  listNativeAccountCapabilities,
  listWebsiteAccountHandoffs,
  resolveAccountCapability,
} from './accountCapabilities';

describe('accountCapabilities', () => {
  it('routes profile/password/notifications/support to native APIs', () => {
    for (const id of [
      'profile',
      'addresses',
      'wishlist',
      'security_password',
      'notifications',
      'support',
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

  it('does not introduce account deletion capability in this phase', () => {
    expect(
      ACCOUNT_CAPABILITIES.some((row) =>
        /delete|close account|deactivate/i.test(row.id + row.label),
      ),
    ).toBe(false);
  });
});
