import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminStoresApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminStoresApiError";
  }
}

export type AdminStoreRecord = {
  id: string;
  code: string;
  name: string;
  slug: string;
  description: string | null;
  logo_path: string | null;
  logo_url: string | null;
  banner_path: string | null;
  banner_url: string | null;
  theme_color: string | null;
  is_active: boolean;
  storefront_enabled: boolean;
  storefront_visible: boolean;
  storefront_featured: boolean;
  storefront_sort_order: number | null;
  sort_order: number;
  created_at: string | null;
  updated_at: string | null;
};

export type AdminStoreWritePayload = {
  name: string;
  slug?: string;
  code?: string;
  description?: string | null;
  theme_color?: string | null;
  is_active?: boolean;
  storefront_enabled?: boolean;
  storefront_visible?: boolean;
  storefront_featured?: boolean;
  storefront_sort_order?: number | null;
  sort_order?: number;
};

export function canViewStores(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "stores.view");
}

export function canCreateStores(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "stores.create");
}

export function canUpdateStores(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "stores.update");
}

async function parseStoreResponse(response: Response): Promise<AdminStoreRecord> {
  let payload: {
    success?: boolean;
    message?: string;
    data?: AdminStoreRecord;
  } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminStoresApiError(
      payload.message?.trim() || "Store request failed.",
      response.status,
    );
  }

  return payload.data;
}

export async function fetchAdminStoreList(): Promise<AdminStoreRecord[]> {
  const response = await fetch("/api/admin/stores", {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });

  let payload: {
    success?: boolean;
    message?: string;
    data?: AdminStoreRecord[];
  } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !Array.isArray(payload.data)) {
    throw new AdminStoresApiError(
      payload.message?.trim() || "Unable to load stores.",
      response.status,
    );
  }

  return payload.data;
}

export async function fetchAdminStore(id: string): Promise<AdminStoreRecord> {
  const response = await fetch(`/api/admin/stores/${encodeURIComponent(id)}`, {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  return parseStoreResponse(response);
}

export async function createAdminStore(
  body: AdminStoreWritePayload & { code: string },
): Promise<AdminStoreRecord> {
  const response = await fetch("/api/admin/stores", {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  return parseStoreResponse(response);
}

export async function updateAdminStore(
  id: string,
  body: AdminStoreWritePayload,
): Promise<AdminStoreRecord> {
  const response = await fetch(`/api/admin/stores/${encodeURIComponent(id)}`, {
    method: "PUT",
    credentials: "include",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  return parseStoreResponse(response);
}

export async function updateAdminStoreStatus(
  id: string,
  isActive: boolean,
): Promise<AdminStoreRecord> {
  const response = await fetch(`/api/admin/stores/${encodeURIComponent(id)}/status`, {
    method: "PATCH",
    credentials: "include",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ is_active: isActive }),
  });
  return parseStoreResponse(response);
}

export async function uploadAdminStoreBranding(
  id: string,
  files: { logo?: File | null; banner?: File | null },
): Promise<AdminStoreRecord> {
  const formData = new FormData();
  if (files.logo) formData.append("logo", files.logo, files.logo.name);
  if (files.banner) formData.append("banner", files.banner, files.banner.name);

  const response = await fetch(`/api/admin/stores/${encodeURIComponent(id)}/branding`, {
    method: "POST",
    credentials: "include",
    body: formData,
  });
  return parseStoreResponse(response);
}
