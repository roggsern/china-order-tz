import { create } from 'zustand';

import type { AdminIdentity, AuthStatus, BootstrapStatus } from './types';

type AdminAuthState = {
  status: AuthStatus;
  bootstrapStatus: BootstrapStatus;
  admin: AdminIdentity | null;
  setBootstrapping: () => void;
  setAuthenticated: (admin: AdminIdentity) => void;
  setUnauthenticated: () => void;
  setBootstrapReady: () => void;
};

export const useAdminAuthStore = create<AdminAuthState>((set) => ({
  status: 'unknown',
  bootstrapStatus: 'idle',
  admin: null,
  setBootstrapping: () => set({ bootstrapStatus: 'bootstrapping' }),
  setAuthenticated: (admin) =>
    set({ status: 'authenticated', admin, bootstrapStatus: 'ready' }),
  setUnauthenticated: () =>
    set({ status: 'unauthenticated', admin: null, bootstrapStatus: 'ready' }),
  setBootstrapReady: () => set({ bootstrapStatus: 'ready' }),
}));

export function selectAdminPermissions(state: AdminAuthState): string[] {
  return state.admin?.permissions ?? [];
}

export function selectIsAuthenticated(state: AdminAuthState): boolean {
  return state.status === 'authenticated';
}
