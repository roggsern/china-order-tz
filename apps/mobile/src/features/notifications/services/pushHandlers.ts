import * as Notifications from 'expo-notifications';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { extractNotificationContentData } from '../utils/notificationData';
import {
  markNotificationResponseConsumed,
  queuePendingNotificationHref,
} from '../utils/pendingNotificationNavigation';
import { resolveNotificationDestination } from '../utils/resolveNotificationDestination';

export function navigateToNotificationDestination(href: string): void {
  const authStatus = useAuthStore.getState().status;
  if (authStatus === 'authenticated') {
    router.push(href as never);
    return;
  }
  router.push(buildLoginHref(href) as never);
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

  const data = extractNotificationContentData(
    response.notification.request.content.data,
  );
  const href = resolveNotificationDestination(data);
  return href;
}

export function handleNotificationResponseNavigation(
  response: Notifications.NotificationResponse | null | undefined,
  options?: { deferIfUnauthenticated?: boolean },
): string | null {
  const href = consumeNotificationResponse(response);
  if (!href) return null;

  const authStatus = useAuthStore.getState().status;
  if (options?.deferIfUnauthenticated && authStatus !== 'authenticated') {
    queuePendingNotificationHref(href);
    return href;
  }

  navigateToNotificationDestination(href);
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
