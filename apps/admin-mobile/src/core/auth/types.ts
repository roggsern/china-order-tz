export type AdminRole = {
  id?: string;
  name?: string;
  slug?: string;
};

export type AdminIdentity = {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  is_super_admin: boolean;
  is_active: boolean;
  permissions: string[];
  role: AdminRole | null;
};

export type AuthStatus = 'unknown' | 'authenticated' | 'unauthenticated';
export type BootstrapStatus = 'idle' | 'bootstrapping' | 'ready';
