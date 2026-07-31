export type AdminMe = {
  id: string;
  name: string;
  email: string;
  is_super_admin?: boolean;
  permissions?: string[];
};

export class AdminMeApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminMeApiError";
  }
}

export async function fetchAdminMe(): Promise<AdminMe> {
  const response = await fetch("/api/admin/me", {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: AdminMe;
  };

  if (!response.ok || payload.success === false || !payload.data) {
    throw new AdminMeApiError(
      payload.message?.trim() || "Unable to load admin profile.",
      response.status,
    );
  }

  return payload.data;
}

export function resolveAdminPermissions(admin: AdminMe | null | undefined): string[] | undefined {
  if (!admin) {
    return undefined;
  }

  if (admin.is_super_admin) {
    return undefined;
  }

  return admin.permissions ?? [];
}

export function hasAdminPermission(
  permissions: string[] | undefined,
  slug: string,
): boolean {
  if (permissions === undefined) {
    return true;
  }

  return permissions.includes(slug);
}
