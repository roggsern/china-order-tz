import type { QueryClient } from '@tanstack/react-query';

export function checkoutPrepareQueryKey() {
  return ['checkout', 'prepare'] as const;
}

export function checkoutSessionQueryKey(sessionId: string | null) {
  return ['checkout', 'session', sessionId] as const;
}

export async function invalidateAfterCheckoutCancel(
  queryClient: QueryClient,
  sessionId?: string | null,
): Promise<void> {
  if (sessionId) {
    queryClient.removeQueries({ queryKey: checkoutSessionQueryKey(sessionId) });
  }
  await queryClient.invalidateQueries({ queryKey: checkoutPrepareQueryKey() });
}
