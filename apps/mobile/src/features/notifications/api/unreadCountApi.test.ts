import { mapUnreadNotificationCount } from './unreadCountApi';

describe('mapUnreadNotificationCount', () => {
  it('maps canonical unread_count from envelope', () => {
    expect(mapUnreadNotificationCount({ data: { unread_count: 7 } })).toBe(7);
  });

  it('returns 0 for malformed payloads', () => {
    expect(mapUnreadNotificationCount({ data: { unread_count: -1 } })).toBe(0);
    expect(mapUnreadNotificationCount(null)).toBe(0);
    expect(mapUnreadNotificationCount({ data: {} })).toBe(0);
  });
});
