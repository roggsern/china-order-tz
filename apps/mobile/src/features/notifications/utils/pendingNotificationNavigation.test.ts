import {
  markNotificationResponseConsumed,
  queuePendingNotificationHref,
  consumePendingNotificationHref,
  resetPendingNotificationNavigationForTests,
} from './pendingNotificationNavigation';

describe('pendingNotificationNavigation', () => {
  beforeEach(() => {
    resetPendingNotificationNavigationForTests();
  });

  it('consumes a notification response id only once', () => {
    expect(markNotificationResponseConsumed('resp-1')).toBe(true);
    expect(markNotificationResponseConsumed('resp-1')).toBe(false);
  });

  it('preserves unauthenticated destination for returnTo via pending href', () => {
    queuePendingNotificationHref('/(app)/orders/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee');
    expect(consumePendingNotificationHref()).toBe(
      '/(app)/orders/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    );
    expect(consumePendingNotificationHref()).toBeNull();
  });
});
