import {
  deactivateAdminPushToken,
  mapAdminPushTokenRegistration,
  registerAdminPushToken,
} from './adminPushApi';

describe('adminPushApi mappers', () => {
  it('maps registration response', () => {
    const mapped = mapAdminPushTokenRegistration({
      id: 'tok-1',
      provider: 'expo',
      platform: 'android',
      installation_id: '11111111-1111-4111-8111-111111111111',
      is_active: true,
      last_seen_at: '2026-08-16T10:00:00Z',
      created_at: '2026-08-16T09:00:00Z',
      updated_at: '2026-08-16T10:00:00Z',
    });

    expect(mapped).toEqual({
      id: 'tok-1',
      provider: 'expo',
      platform: 'android',
      installationId: '11111111-1111-4111-8111-111111111111',
      isActive: true,
      lastSeenAt: '2026-08-16T10:00:00Z',
      createdAt: '2026-08-16T09:00:00Z',
      updatedAt: '2026-08-16T10:00:00Z',
    });
  });

  it('returns null for invalid registration response', () => {
    expect(mapAdminPushTokenRegistration({ provider: 'expo' })).toBeNull();
  });
});

describe('adminPushApi endpoints', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    global.fetch = jest.fn();
  });

  it('registers admin push token', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 200,
      text: async () =>
        JSON.stringify({
          success: true,
          data: {
            id: 'tok-1',
            provider: 'expo',
            platform: 'android',
            installation_id: '11111111-1111-4111-8111-111111111111',
            is_active: true,
          },
        }),
    });

    const result = await registerAdminPushToken({
      pushToken: 'ExponentPushToken[abc]',
      provider: 'expo',
      platform: 'android',
      installationId: '11111111-1111-4111-8111-111111111111',
      appVersion: '0.1.0',
      deviceName: 'Pixel',
    });

    expect(result.id).toBe('tok-1');
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/admin/devices/push-tokens'),
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          push_token: 'ExponentPushToken[abc]',
          provider: 'expo',
          platform: 'android',
          installation_id: '11111111-1111-4111-8111-111111111111',
          app_version: '0.1.0',
          device_name: 'Pixel',
        }),
      }),
    );
  });

  it('deactivates admin push token', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 200,
      text: async () =>
        JSON.stringify({
          success: true,
          data: { deactivated: 1 },
        }),
    });

    const count = await deactivateAdminPushToken({
      installationId: '11111111-1111-4111-8111-111111111111',
      pushToken: 'ExponentPushToken[abc]',
    });

    expect(count).toBe(1);
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/admin/devices/push-tokens'),
      expect.objectContaining({
        method: 'DELETE',
        body: JSON.stringify({
          installation_id: '11111111-1111-4111-8111-111111111111',
          push_token: 'ExponentPushToken[abc]',
        }),
      }),
    );
  });
});
