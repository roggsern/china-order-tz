import { useEffect, useRef } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import * as Notifications from 'expo-notifications';

import { useAdminAuthStore } from '@/src/core/auth';

import {
  handleAdminPushTokenRotation,
  registerAdminPush,
} from './pushRegistration';
import {
  consumeLastNotificationResponseOnLaunch,
  handleNotificationResponseNavigation,
  navigateToAdminPushDestination,
} from './pushHandlers';
import { consumePendingNotificationHref } from './pendingNotificationNavigation';

/**
 * Authenticated admin push bootstrap: permission → token → register → listeners.
 * Mount once under the root QueryClientProvider after auth bootstrap.
 */
export function useAdminPushBootstrap(): void {
  const authStatus = useAdminAuthStore((s) => s.status);
  const registeredForAdmin = useRef<string | null>(null);
  const coldStartConsumed = useRef(false);

  useEffect(() => {
    if (authStatus === 'unknown') return;

    const pending = consumePendingNotificationHref();
    if (pending) {
      navigateToAdminPushDestination(pending);
      return;
    }

    if (!coldStartConsumed.current) {
      coldStartConsumed.current = true;
      void consumeLastNotificationResponseOnLaunch();
    }
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== 'authenticated') {
      registeredForAdmin.current = null;
      return;
    }

    const adminId = useAdminAuthStore.getState().admin?.id ?? 'authenticated';
    if (registeredForAdmin.current !== adminId) {
      registeredForAdmin.current = adminId;
      void registerAdminPush({ adminId });
    }

    const responseSub = Notifications.addNotificationResponseReceivedListener((response) => {
      handleNotificationResponseNavigation(response);
    });

    const tokenSub = Notifications.addPushTokenListener(() => {
      void handleAdminPushTokenRotation(undefined, adminId);
    });

    const onAppState = (_next: AppStateStatus) => {
      // Resume only — do not re-register on every foreground (avoids storms).
    };
    const appStateSub = AppState.addEventListener('change', onAppState);

    return () => {
      responseSub.remove();
      tokenSub.remove();
      appStateSub.remove();
    };
  }, [authStatus]);
}
