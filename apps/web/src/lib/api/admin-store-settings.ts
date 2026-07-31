import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminStoreSettingsApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminStoreSettingsApiError";
  }
}

export type StoreBusinessSettings = {
  display_name: string;
  phone: string;
  email: string;
  address: string;
};

export type StoreReceiptSettingsSection = {
  footer_message: string;
  show_logo: boolean;
};

export type StoreCustomerSettings = {
  support_phone: string;
  support_email: string;
};

export type StoreSocialSettings = {
  instagram: string;
  facebook: string;
  tiktok: string;
};

export type AdminStoreSettings = {
  store_id: string;
  store_code: string;
  store_name: string;
  business: StoreBusinessSettings;
  receipt: StoreReceiptSettingsSection;
  customer: StoreCustomerSettings;
  social: StoreSocialSettings;
};

export type UpdateAdminStoreSettingsInput = {
  business?: Partial<StoreBusinessSettings>;
  receipt?: Partial<StoreReceiptSettingsSection>;
  customer?: Partial<StoreCustomerSettings>;
  social?: Partial<StoreSocialSettings>;
};

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
  throw new AdminStoreSettingsApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export function canViewStoreSettings(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "stores.view");
}

export function canManageStoreSettings(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "stores.manage");
}

export async function fetchAdminStoreSettings(storeId: string): Promise<AdminStoreSettings> {
  const response = await fetch(`/api/admin/stores/${encodeURIComponent(storeId)}/settings`, {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminStoreSettings;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load store settings.");
  }

  if (!payload.data) {
    throw new AdminStoreSettingsApiError("Invalid store settings response.", response.status);
  }

  return payload.data;
}

export async function updateAdminStoreSettings(
  storeId: string,
  input: UpdateAdminStoreSettingsInput,
): Promise<AdminStoreSettings> {
  const response = await fetch(`/api/admin/stores/${encodeURIComponent(storeId)}/settings`, {
    method: "PUT",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminStoreSettings;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to update store settings.");
  }

  if (!payload.data) {
    throw new AdminStoreSettingsApiError("Invalid store settings response.", response.status);
  }

  return payload.data;
}

export function emptyStoreSettingsSections(): Pick<
  AdminStoreSettings,
  "business" | "receipt" | "customer" | "social"
> {
  return {
    business: { display_name: "", phone: "", email: "", address: "" },
    receipt: { footer_message: "", show_logo: true },
    customer: { support_phone: "", support_email: "" },
    social: { instagram: "", facebook: "", tiktok: "" },
  };
}
