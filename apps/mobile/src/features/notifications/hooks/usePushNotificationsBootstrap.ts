import { useEffect, useRef } from 'react';
import { useAuthStore } from '@/src/core/auth';
import { attachPushRuntimeListeners } from '../services/pushListeners';
import {
  configureForegroundNotificationHandler,
  handleExpoPushTokenRotation,
  registerPushForCurrentUser,
} from '../services/pushRegistration';
import {
  consumeLastNotificationResponseOnLaunch,
  handleNotificationResponseNavigation,
  navigateToNotificationDestination,
} from '../services/pushHandlers';
import { consumePendingNotificationHref } from '../utils/pendingNotificationNavigation';
import { useInvalidateNotificationQueries } from './useUnreadNotificationCount';

let foregroundHandlerConfigured = false;

/**
 * Authenticated push bootstrap: permission → token → register → listeners.
 * Safe to mount once under the root QueryClientProvider after auth bootstrap.
 */
export function usePushNotificationsBootstrap(): void {
  const authStatus = useAuthStore((s) => s.status);
  const invalidateNotifications = useInvalidateNotificationQueries();
  const invalidateRef = useRef(invalidateNotifications);
  useEffect(() => {
    invalidateRef.current = invalidateNotifications;
  }, [invalidateNotifications]);

  const registeredForUser = useRef<string | null>(null);
  const coldStartConsumed = useRef(false);

  useEffect(() => {
    if (!foregroundHandlerConfigured) {
      configureForegroundNotificationHandler();
      foregroundHandlerConfigured = true;
    }
  }, []);

  // Cold-start / pending navigation once auth status is known (authenticated or not).
  useEffect(() => {
    if (authStatus === 'unknown') return;

    const pending = consumePendingNotificationHref();
    if (pending) {
      navigateToNotificationDestination(pending);
      return;
    }

    if (!coldStartConsumed.current) {
      coldStartConsumed.current = true;
      void consumeLastNotificationResponseOnLaunch();
    }
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== 'authenticated') {
      registeredForUser.current = null;
      return;
    }

    const userId = useAuthStore.getState().user?.id ?? 'authenticated';
    if (registeredForUser.current !== userId) {
      registeredForUser.current = userId;
      void registerPushForCurrentUser({ userId });
    }

    const detachListeners = attachPushRuntimeListeners({
      onReceived: () => {
        // Arrival only — refresh inbox/unread; do not navigate.
        invalidateRef.current();
      },
      onResponse: (response) => {
        handleNotificationResponseNavigation(response);
        invalidateRef.current();
      },
      onToken: () => {
        // Native FCM/APNs token rotation — coalesce via registerPushForCurrentUser.
        // Must NOT resetPushRegistrationState() (clears in-flight → parallel storms).
        void handleExpoPushTokenRotation(undefined, userId);
      },
    });

    return detachListeners;
    // Intentionally depend only on authStatus — invalidateNotifications is read via ref.
  }, [authStatus]);
}
