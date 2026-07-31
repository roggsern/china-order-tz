import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminStoreTeamApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminStoreTeamApiError";
  }
}

export type StoreOperationalScope = "store_manager" | "store_operator" | "store_viewer";

export type StoreTeamMember = {
  id: string;
  store_id: string;
  admin_id: string;
  operational_scope: StoreOperationalScope;
  operational_scope_label: string;
  assignment_type: string;
  is_active: boolean;
  is_currently_active: boolean;
  starts_at: string | null;
  ends_at: string | null;
  assigned_by: string | null;
  admin?: {
    id: string;
    name: string;
    email: string;
    is_active?: boolean;
    role?: { id: string; name: string; slug: string } | null;
  } | null;
  created_at: string | null;
  updated_at: string | null;
};

export function canViewStoreTeam(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "stores.team.view");
}

export function canManageStoreTeam(permissions: string[] | undefined): boolean {
  return (
    hasAdminPermission(permissions, "stores.team.manage") ||
    hasAdminPermission(permissions, "stores.assign")
  );
}

export function scopeLabel(scope: StoreOperationalScope): string {
  switch (scope) {
    case "store_manager":
      return "Store Manager";
    case "store_operator":
      return "Store Operator";
    case "store_viewer":
      return "Store Viewer";
    default:
      return scope;
  }
}

async function parseTeamResponse(response: Response): Promise<StoreTeamMember[]> {
  let payload: { success?: boolean; message?: string; data?: StoreTeamMember[] } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }
  if (!response.ok || !Array.isArray(payload.data)) {
    throw new AdminStoreTeamApiError(
      payload.message?.trim() || "Unable to load store team.",
      response.status,
    );
  }
  return payload.data;
}

async function parseMemberResponse(response: Response): Promise<StoreTeamMember> {
  let payload: { success?: boolean; message?: string; data?: StoreTeamMember } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }
  if (!response.ok || !payload.data) {
    throw new AdminStoreTeamApiError(
      payload.message?.trim() || "Store team request failed.",
      response.status,
    );
  }
  return payload.data;
}

export async function fetchStoreTeam(storeId: string): Promise<StoreTeamMember[]> {
  const response = await fetch(`/api/admin/stores/${encodeURIComponent(storeId)}/team`, {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  return parseTeamResponse(response);
}

export async function assignStoreTeamMember(
  storeId: string,
  body: { admin_id: string; operational_scope: StoreOperationalScope },
): Promise<StoreTeamMember> {
  const response = await fetch(`/api/admin/stores/${encodeURIComponent(storeId)}/team`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  return parseMemberResponse(response);
}

export async function updateStoreTeamMember(
  storeId: string,
  adminId: string,
  body: { operational_scope: StoreOperationalScope },
): Promise<StoreTeamMember> {
  const response = await fetch(
    `/api/admin/stores/${encodeURIComponent(storeId)}/team/${encodeURIComponent(adminId)}`,
    {
      method: "PUT",
      credentials: "include",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(body),
    },
  );
  return parseMemberResponse(response);
}

export async function removeStoreTeamMember(storeId: string, adminId: string): Promise<StoreTeamMember> {
  const response = await fetch(
    `/api/admin/stores/${encodeURIComponent(storeId)}/team/${encodeURIComponent(adminId)}`,
    {
      method: "DELETE",
      credentials: "include",
    },
  );
  return parseMemberResponse(response);
}

export async function fetchMyStores(): Promise<
  { id: string; code: string; name: string; is_active: boolean }[]
> {
  const response = await fetch("/api/admin/my-stores", {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  let payload: {
    success?: boolean;
    message?: string;
    data?: { id: string; code: string; name: string; is_active: boolean }[];
  } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }
  if (!response.ok || !Array.isArray(payload.data)) {
    throw new AdminStoreTeamApiError(
      payload.message?.trim() || "Unable to load your stores.",
      response.status,
    );
  }
  return payload.data;
}
