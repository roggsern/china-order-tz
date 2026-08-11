import { create } from 'zustand';
import type { User } from '@/src/shared/types/user';

/** Session decision after bootstrap (or login/logout). */
export type AuthStatus = 'unknown' | 'authenticated' | 'unauthenticated';

/** Startup restore lifecycle — independent of authenticated state. */
export type BootstrapStatus = 'pending' | 'complete';

type AuthState = {
  status: AuthStatus;
  user: User | null;
  bootstrapStatus: BootstrapStatus;
  setAuthenticated: (user: User) => void;
  setUnauthenticated: () => void;
  setBootstrapping: () => void;
};

/**
 * In-memory auth state only.
 * Never persist the access token here — SecureStore owns the token.
 */
export const useAuthStore = create<AuthState>((set) => ({
  status: 'unknown',
  user: null,
  bootstrapStatus: 'pending',
  setBootstrapping: () =>
    set({
      status: 'unknown',
      bootstrapStatus: 'pending',
    }),
  setAuthenticated: (user) =>
    set({
      status: 'authenticated',
      user,
      bootstrapStatus: 'complete',
    }),
  setUnauthenticated: () =>
    set({
      status: 'unauthenticated',
      user: null,
      bootstrapStatus: 'complete',
    }),
}));
