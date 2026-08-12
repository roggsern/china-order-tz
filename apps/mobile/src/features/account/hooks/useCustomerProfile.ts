import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AUTHENTICATED_QUERY_META, useAuthStore } from '@/src/core/auth';
import {
  fetchCustomerProfile,
  updateCustomerProfile,
  fetchCurrentUser,
  type UpdateProfileInput,
} from '../api/profileApi';
import { userSchema } from '@/src/shared/types/user';

export function profileQueryKey() {
  return ['account', 'profile'] as const;
}

export function useCustomerProfile(enabled = true) {
  const authStatus = useAuthStore((s) => s.status);
  return useQuery({
    queryKey: profileQueryKey(),
    queryFn: fetchCustomerProfile,
    enabled: enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useUpdateProfileMutation() {
  const queryClient = useQueryClient();
  const setAuthenticated = useAuthStore((s) => s.setAuthenticated);

  return useMutation({
    mutationFn: (input: UpdateProfileInput) => updateCustomerProfile(input),
    onSuccess: async (profile) => {
      queryClient.setQueryData(profileQueryKey(), profile);
      try {
        const me = await fetchCurrentUser();
        const parsed = userSchema.safeParse(me);
        if (parsed.success) {
          setAuthenticated(parsed.data);
        }
      } catch {
        // Profile saved; auth card refresh is best-effort.
      }
    },
  });
}
