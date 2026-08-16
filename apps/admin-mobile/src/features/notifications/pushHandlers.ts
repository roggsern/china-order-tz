import * as Notifications from 'expo-notifications';
import { router } from 'expo-router';

import { useAdminAuthStore } from '@/src/core/auth';

import {
  markNotificationResponseConsumed,
  queuePendingNotificationHref,
} from './pendingNotificationNavigation';
import { ADMIN_DASHBOARD_HREF, resolveAdminPushDestination } from './pushDestinations';

export function navigateToAdminPushDestination(href: string): void {
  const authStatus = useAdminAuthStore.getState().status;
  if (authStatus !== 'authenticated') {
    queuePendingNotificationHref(href);
    return;
  }
  router.push(href as never);
}

/**
 * Handle a notification tap once. Returns destination href if newly consumed.
 */
export function consumeNotificationResponse(
  response: Notifications.NotificationResponse | null | undefined,
): string | null {
  if (!response) return null;

  const responseId = response.notification.request.identifier;
  if (!markNotificationResponseConsumed(responseId)) {
    return null;
  }

  const data = response.notification.request.content.data;
  const href = resolveAdminPushDestination(data) ?? ADMIN_DASHBOARD_HREF;
  return href;
}

export function handleNotificationResponseNavigation(
  response: Notifications.NotificationResponse | null | undefined,
  options?: { deferIfUnauthenticated?: boolean },
): string | null {
  const href = consumeNotificationResponse(response);
  if (!href) return null;

  const authStatus = useAdminAuthStore.getState().status;
  if (options?.deferIfUnauthenticated && authStatus !== 'authenticated') {
    queuePendingNotificationHref(href);
    return href;
  }

  navigateToAdminPushDestination(href);
  return href;
}

export async function consumeLastNotificationResponseOnLaunch(): Promise<string | null> {
  try {
    const response = await Notifications.getLastNotificationResponseAsync();
    const href = handleNotificationResponseNavigation(response, {
      deferIfUnauthenticated: true,
    });
    if (href) {
      await Notifications.clearLastNotificationResponseAsync();
    }
    return href;
  } catch {
    return null;
  }
}
