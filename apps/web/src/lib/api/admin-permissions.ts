import {
  AdminPermissionCatalogEntry,
  AdminPermissionDomainGroup,
} from "@/lib/admin/admin-permission-catalog";

export class AdminPermissionsApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminPermissionsApiError";
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
  throw new AdminPermissionsApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export async function fetchAdminPermissionCatalog(): Promise<{
  permissions: AdminPermissionCatalogEntry[];
  permissions_by_domain: AdminPermissionDomainGroup[];
}> {
  const response = await fetch("/api/admin/permissions", {
    credentials: "include",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJson<{
    success?: boolean;
    data?: {
      permissions?: AdminPermissionCatalogEntry[];
      permissions_by_domain?: AdminPermissionDomainGroup[];
    };
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to load permission catalog.");
  }

  return {
    permissions: Array.isArray(payload.data.permissions) ? payload.data.permissions : [],
    permissions_by_domain: Array.isArray(payload.data.permissions_by_domain)
      ? payload.data.permissions_by_domain
      : [],
  };
}
