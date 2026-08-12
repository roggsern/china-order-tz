import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AUTHENTICATED_QUERY_META, useAuthStore } from '@/src/core/auth';
import { unreadNotificationsQueryKey } from '@/src/features/notifications';
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '../api/notificationsApi';

export function notificationsQueryKey() {
  return ['account', 'notifications'] as const;
}

export function useNotifications(enabled = true) {
  const authStatus = useAuthStore((s) => s.status);
  return useQuery({
    queryKey: notificationsQueryKey(),
    queryFn: async () => (await fetchNotifications()).notifications,
    enabled: enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

function invalidateNotificationCaches(queryClient: ReturnType<typeof useQueryClient>) {
  void queryClient.invalidateQueries({ queryKey: notificationsQueryKey() });
  void queryClient.invalidateQueries({ queryKey: unreadNotificationsQueryKey() });
}

export function useNotificationMutations() {
  const queryClient = useQueryClient();

  const markRead = useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: () => {
      invalidateNotificationCaches(queryClient);
    },
  });

  const markAll = useMutation({
    mutationFn: () => markAllNotificationsRead(),
    onSuccess: () => {
      invalidateNotificationCaches(queryClient);
    },
  });

  return { markRead, markAll };
}
