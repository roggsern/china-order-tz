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

  it('keeps website fallback only for unsupported settings preferences', () => {
    const handoffs = listWebsiteAccountHandoffs();
    expect(handoffs).toHaveLength(1);
    expect(handoffs[0]?.id).toBe('settings');
    expect(handoffs[0]?.webPath).toBe('/account');
  });

  it('does not invent notification preference or fake settings native routes', () => {
    const nativeIds = listNativeAccountCapabilities().map((row) => row.id);
    expect(nativeIds).not.toContain('settings');
    expect(ACCOUNT_CAPABILITIES.some((row) => row.id === 'settings' && row.decision === 'native')).toBe(
      false,
    );
  });
});
