import { hasAdminPermission } from "@/lib/api/admin-me";
import type {
  RolePermissionDraft,
  RolePermissionPreview,
} from "@/lib/admin/admin-role-permission-editor";

export type { RolePermissionDraft, RolePermissionPreview };

export class AdminRolesApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminRolesApiError";
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
  throw new AdminRolesApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export type AdminRoleMatrixSummary = {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  users_count: number;
  permissions_count: number;
};

export type AdminRolePermission = {
  id: string;
  name: string;
  slug: string;
  domain: string;
  description?: string | null;
  risk_tier?: "low" | "medium" | "high";
};

export type AdminRolePermissionGroup = {
  domain: string;
  permissions: AdminRolePermission[];
};

export type AdminRoleAssignedAdmin = {
  id: string;
  name: string;
  email: string;
  is_super_admin: boolean;
  is_active: boolean;
};

export type AdminRoleDetail = {
  role: AdminRoleMatrixSummary;
  assigned_admins: AdminRoleAssignedAdmin[];
  permissions_by_domain: AdminRolePermissionGroup[];
};

export function formatPermissionDomainLabel(domain: string): string {
  return domain
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

export function canManageRolePermissions(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "roles.manage_permissions");
}

export function canViewAdminRoles(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "admins.view");
}

export function sortRoleSummaries(rows: AdminRoleMatrixSummary[]): AdminRoleMatrixSummary[] {
  return [...rows].sort((a, b) => a.name.localeCompare(b.name));
}

export async function fetchAdminRoles(): Promise<AdminRoleMatrixSummary[]> {
  const response = await fetch("/api/admin/roles", {
    credentials: "include",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminRoleMatrixSummary[];
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load roles.");
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchAdminRole(id: string): Promise<AdminRoleDetail> {
  const response = await fetch(`/api/admin/roles/${id}`, {
    credentials: "include",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminRoleDetail;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to load role detail.");
  }

  return payload.data;
}

export async function previewRolePermissionChanges(
  roleId: string,
  draft: RolePermissionDraft,
): Promise<RolePermissionPreview> {
  const response = await fetch(`/api/admin/roles/${roleId}/permissions/preview`, {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(draft),
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: RolePermissionPreview;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to preview permission changes.");
  }

  return payload.data;
}

export async function updateRolePermissions(
  roleId: string,
  draft: RolePermissionDraft,
): Promise<AdminRoleDetail> {
  const response = await fetch(`/api/admin/roles/${roleId}/permissions`, {
    method: "PATCH",
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(draft),
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: AdminRoleDetail;
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to update role permissions.");
  }

  return payload.data;
}
