import { useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';

import { AUTHENTICATED_QUERY_META, useAuthStore } from '@/src/core/auth';

import { notificationUnreadCountQueryKey } from '../api/notificationQueryKeys';
import { fetchUnreadNotificationCount } from '../api/unreadCountApi';

export function unreadNotificationsQueryKey() {
  return notificationUnreadCountQueryKey;
}

export function useUnreadNotificationCount() {
  const authStatus = useAuthStore((s) => s.status);

  return useQuery({
    queryKey: unreadNotificationsQueryKey(),
    queryFn: fetchUnreadNotificationCount,
    enabled: authStatus === 'authenticated',
    staleTime: 30_000,
    meta: AUTHENTICATED_QUERY_META,
  });
}

/** Invalidate inbox list + unread badge (foreground arrival, resume, mark-read). */
export function useInvalidateNotificationQueries(): () => void {
  const queryClient = useQueryClient();

  return useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: ['account', 'notifications'] });
    void queryClient.invalidateQueries({
      queryKey: unreadNotificationsQueryKey(),
    });
  }, [queryClient]);
}
