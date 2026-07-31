import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminAdminsApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminAdminsApiError";
  }
}

async function parseJson<T>(response: Response): Promise<T> {
  try {
    return (await response.json()) as T;
  } catch {
    return {} as T;
  }
}

function throwFromPayload(
  response: Response,
  payload: { message?: string; errors?: Record<string, string[]> },
  fallback: string,
): never {
  const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
  throw new AdminAdminsApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export type AdminRoleSummary = {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
};

export type AdminUserRecord = {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  is_super_admin: boolean;
  is_active: boolean;
  permissions?: string[];
  role?: AdminRoleSummary | null;
  created_at?: string;
  updated_at?: string;
};

export type AdminUserListQuery = {
  search?: string;
  is_active?: boolean;
  role_id?: string;
  page?: number;
  per_page?: number;
};

export type AdminUserActions = {
  canView: boolean;
  canCreate: boolean;
  canUpdate: boolean;
  canActivate: boolean;
  canDeactivate: boolean;
  canAssignRole: boolean;
};

export function resolveAdminUserActions(
  permissions: string[] | undefined,
  options?: { targetIsSuperAdmin?: boolean; isSelf?: boolean },
): AdminUserActions {
  const targetIsSuperAdmin = options?.targetIsSuperAdmin ?? false;
  const isSelf = options?.isSelf ?? false;

  return {
    canView: hasAdminPermission(permissions, "admins.view"),
    canCreate: hasAdminPermission(permissions, "admins.create"),
    canUpdate:
      hasAdminPermission(permissions, "admins.update") &&
      (!targetIsSuperAdmin || permissions === undefined),
    canActivate: hasAdminPermission(permissions, "admins.activate") && !targetIsSuperAdmin,
    canDeactivate:
      hasAdminPermission(permissions, "admins.deactivate") &&
      !targetIsSuperAdmin &&
      !isSelf,
    canAssignRole:
      hasAdminPermission(permissions, "admins.assign_roles") &&
      (!targetIsSuperAdmin || permissions === undefined) &&
      (!isSelf || permissions === undefined),
  };
}

export async function fetchAdminUsers(query?: AdminUserListQuery): Promise<{
  data: AdminUserRecord[];
  meta?: { current_page?: number; last_page?: number; total?: number };
}> {
  const params = new URLSearchParams();
  if (query?.search) params.set("search", query.search);
  if (query?.is_active !== undefined) params.set("is_active", query.is_active ? "1" : "0");
  if (query?.role_id) params.set("role_id", query.role_id);
  if (query?.page) params.set("page", String(query.page));
  params.set("per_page", String(query?.per_page ?? 20));

  const response = await fetch(`/api/admin/admins?${params}`, {
    credentials: "include",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminUserRecord[];
    meta?: { current_page?: number; last_page?: number; total?: number };
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load admin users.");
  }

  return {
    data: Array.isArray(payload.data) ? payload.data : [],
    meta: payload.meta,
  };
}

export async function fetchAdminUser(id: string): Promise<AdminUserRecord> {
  const response = await fetch(`/api/admin/admins/${id}`, {
    credentials: "include",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminUserRecord;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to load admin user.");
  }

  return payload.data;
}

export async function fetchAdminAssignableRoles(): Promise<AdminRoleSummary[]> {
  const response = await fetch("/api/admin/roles?assignable=1", {
    credentials: "include",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminRoleSummary[];
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load roles.");
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createAdminUser(input: {
  name: string;
  email: string;
  password: string;
  role_id: string;
  phone?: string | null;
  is_active?: boolean;
}): Promise<AdminUserRecord> {
  const response = await fetch("/api/admin/admins", {
    method: "POST",
    credentials: "include",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminUserRecord;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to create admin user.");
  }

  return payload.data;
}

export async function updateAdminUser(
  id: string,
  input: {
    name?: string;
    email?: string;
    phone?: string | null;
    password?: string;
  },
): Promise<AdminUserRecord> {
  const response = await fetch(`/api/admin/admins/${id}`, {
    method: "PATCH",
    credentials: "include",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminUserRecord;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to update admin user.");
  }

  return payload.data;
}

export async function activateAdminUser(id: string): Promise<AdminUserRecord> {
  const response = await fetch(`/api/admin/admins/${id}/activate`, {
    method: "PATCH",
    credentials: "include",
    headers: { Accept: "application/json" },
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminUserRecord;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to activate admin user.");
  }

  return payload.data;
}

export async function deactivateAdminUser(id: string): Promise<AdminUserRecord> {
  const response = await fetch(`/api/admin/admins/${id}/deactivate`, {
    method: "PATCH",
    credentials: "include",
    headers: { Accept: "application/json" },
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminUserRecord;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to deactivate admin user.");
  }

  return payload.data;
}

export async function assignAdminUserRole(
  id: string,
  roleId: string,
): Promise<AdminUserRecord> {
  const response = await fetch(`/api/admin/admins/${id}/role`, {
    method: "PATCH",
    credentials: "include",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ role_id: roleId }),
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminUserRecord;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to assign admin role.");
  }

  return payload.data;
}
