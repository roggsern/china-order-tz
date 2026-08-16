import { z } from 'zod';

import { apiClient, apiRequest } from '@/src/core/api';
import type { AdminIdentity } from '@/src/core/auth/types';

const adminRoleSchema = z
  .object({
    id: z.string().optional(),
    name: z.string().optional(),
    slug: z.string().optional(),
  })
  .nullable()
  .optional();

const adminResourceSchema = z.object({
  id: z.string(),
  name: z.string(),
  email: z.string(),
  phone: z.string().nullable().optional(),
  is_super_admin: z.boolean(),
  is_active: z.boolean(),
  permissions: z.array(z.string()),
  role: adminRoleSchema,
});

export type AdminResource = z.infer<typeof adminResourceSchema>;

export function mapAdminResource(raw: unknown): AdminIdentity {
  const parsed = adminResourceSchema.parse(raw);
  return {
    id: parsed.id,
    name: parsed.name,
    email: parsed.email,
    phone: parsed.phone ?? null,
    is_super_admin: parsed.is_super_admin,
    is_active: parsed.is_active,
    permissions: parsed.permissions,
    role: parsed.role ?? null,
  };
}

export type LoginInput = {
  email: string;
  password: string;
};

export type LoginResult = {
  token: string;
  tokenType: string;
  admin: AdminIdentity;
};

export async function loginAdmin(input: LoginInput): Promise<LoginResult> {
  const envelope = await apiRequest<AdminResource>({
    method: 'POST',
    path: '/admin/login',
    body: {
      email: input.email.trim().toLowerCase(),
      password: input.password,
    },
    token: null,
  });

  const loginBody = envelope as typeof envelope & {
    token?: string;
    token_type?: string;
  };

  const token = loginBody.token;
  if (!token) {
    throw new Error('Login response missing token');
  }

  return {
    token,
    tokenType: loginBody.token_type ?? 'Bearer',
    admin: mapAdminResource(envelope.data),
  };
}

export async function fetchCurrentAdmin(): Promise<AdminIdentity> {
  const envelope = await apiClient.get<AdminResource>('/admin/me');
  return mapAdminResource(envelope.data);
}

export async function logoutAdmin(options?: { installation_id?: string }): Promise<void> {
  const body =
    options?.installation_id && options.installation_id.trim()
      ? { installation_id: options.installation_id.trim() }
      : undefined;
  await apiClient.post('/admin/logout', body);
}
