import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AUTHENTICATED_QUERY_META, useAuthStore } from '@/src/core/auth';
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

export function useNotificationMutations() {
  const queryClient = useQueryClient();

  const markRead = useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: notificationsQueryKey() });
    },
  });

  const markAll = useMutation({
    mutationFn: () => markAllNotificationsRead(),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: notificationsQueryKey() });
    },
  });

  return { markRead, markAll };
}
