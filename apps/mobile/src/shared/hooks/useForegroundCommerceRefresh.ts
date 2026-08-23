import { useEffect, useRef } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';
import { useAuthStore } from '@/src/core/auth';
import {
  FOREGROUND_COMMERCE_QUERY_PREFIXES,
  shouldRunForegroundCommerceRefresh,
} from './foregroundCommerceRefresh';

/**
 * Targeted resume refresh — cart/orders/checkout/payment/unread only.
 * Does not refetch catalog, search, or homepage.
 */
export function useForegroundCommerceRefresh(): void {
  const queryClient = useQueryClient();
  const authStatus = useAuthStore((s) => s.status);
  const previous = useRef<AppStateStatus>(AppState.currentState);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', (next) => {
      const prev = previous.current;
      previous.current = next;
      if (!shouldRunForegroundCommerceRefresh(prev, next)) return;
      if (authStatus !== 'authenticated') return;

      for (const queryKey of FOREGROUND_COMMERCE_QUERY_PREFIXES) {
        void queryClient.invalidateQueries({ queryKey: [...queryKey] });
      }
    });

    return () => subscription.remove();
  }, [authStatus, queryClient]);
}
