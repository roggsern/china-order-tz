import { useEffect, useRef } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import * as Notifications from 'expo-notifications';
import { useAuthStore } from '@/src/core/auth';
import {
  configureForegroundNotificationHandler,
  registerPushForCurrentUser,
  resetPushRegistrationState,
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
      // Force re-register after account switch (in-memory cache cleared on logout).
      void registerPushForCurrentUser();
    }

    const receivedSub = Notifications.addNotificationReceivedListener(() => {
      // Arrival only — refresh inbox/unread; do not navigate.
      invalidateNotifications();
    });

    const responseSub = Notifications.addNotificationResponseReceivedListener(
      (response) => {
        handleNotificationResponseNavigation(response);
        invalidateNotifications();
      },
    );

    const tokenSub = Notifications.addPushTokenListener(() => {
      resetPushRegistrationState();
      void registerPushForCurrentUser();
    });

    const onAppState = (next: AppStateStatus) => {
      if (next === 'active') {
        invalidateNotifications();
      }
    };
    const appStateSub = AppState.addEventListener('change', onAppState);

    return () => {
      receivedSub.remove();
      responseSub.remove();
      tokenSub.remove();
      appStateSub.remove();
    };
  }, [authStatus, invalidateNotifications]);
}
