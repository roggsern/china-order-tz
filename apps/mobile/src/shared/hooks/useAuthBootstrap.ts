import { useEffect } from 'react';
import { bootstrapAuth, useAuthStore, type BootstrapStatus } from '@/src/core/auth';

/**
 * Runs once on app start. Completes when SecureStore + optional GET /me finish.
 */
export function useAuthBootstrap(): BootstrapStatus {
  const bootstrapStatus = useAuthStore((s) => s.bootstrapStatus);

  useEffect(() => {
    void bootstrapAuth();
  }, []);

  return bootstrapStatus;
}
