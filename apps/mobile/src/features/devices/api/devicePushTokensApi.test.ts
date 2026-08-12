import {
  mapDevicePushTokenRegistration,
  type RegisterDevicePushTokenInput,
} from './devicePushTokensApi';

describe('devicePushTokensApi', () => {
  it('maps sanitized registration resource without requiring push_token in response', () => {
    const mapped = mapDevicePushTokenRegistration({
      id: 'tok-1',
      provider: 'expo',
      platform: 'android',
      installation_id: '11111111-1111-4111-8111-111111111111',
      is_active: true,
      last_seen_at: '2026-08-12T00:00:00Z',
      created_at: '2026-08-12T00:00:00Z',
      updated_at: '2026-08-12T00:00:00Z',
    });

    expect(mapped).toEqual({
      id: 'tok-1',
      provider: 'expo',
      platform: 'android',
      installationId: '11111111-1111-4111-8111-111111111111',
      isActive: true,
      lastSeenAt: '2026-08-12T00:00:00Z',
      createdAt: '2026-08-12T00:00:00Z',
      updatedAt: '2026-08-12T00:00:00Z',
    });
  });

  it('rejects incomplete resources', () => {
    expect(mapDevicePushTokenRegistration({ id: 'tok-1' })).toBeNull();
  });

  it('keeps register input typed without inventing tokens', () => {
    const input: RegisterDevicePushTokenInput = {
      pushToken: 'ExponentPushToken[real-token-from-expo]',
      provider: 'expo',
      platform: 'android',
      installationId: '11111111-1111-4111-8111-111111111111',
    };
    expect(input.provider).toBe('expo');
  });
});
