import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminFeatureConfigApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminFeatureConfigApiError";
  }
}

export type FeatureFlags = {
  wishlist: boolean;
  reviews: boolean;
  new_checkout: boolean;
};

export type AdminFeatureConfig = {
  maintenance_mode: boolean;
  maintenance_message: string;
  flags: FeatureFlags;
  allowed_flags?: string[];
  enabled_features?: string[];
};

export type UpdateAdminFeatureConfigInput = {
  maintenance_mode?: boolean;
  maintenance_message?: string;
  flags?: Partial<FeatureFlags>;
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
  throw new AdminFeatureConfigApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export function canViewFeatureConfig(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "features.view");
}

export function canManageFeatureConfig(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "features.manage");
}

export async function fetchAdminFeatureConfig(): Promise<AdminFeatureConfig> {
  const response = await fetch("/api/admin/features/config", {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminFeatureConfig;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load feature configuration.");
  }

  if (!payload.data) {
    throw new AdminFeatureConfigApiError("Invalid feature config response.", response.status);
  }

  return payload.data;
}

export async function updateAdminFeatureConfig(
  input: UpdateAdminFeatureConfigInput,
): Promise<AdminFeatureConfig> {
  const response = await fetch("/api/admin/features/config", {
    method: "PUT",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminFeatureConfig;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to update feature configuration.");
  }

  if (!payload.data) {
    throw new AdminFeatureConfigApiError("Invalid feature config response.", response.status);
  }

  return payload.data;
}

export const FEATURE_FLAG_LABELS: Record<keyof FeatureFlags, string> = {
  wishlist: "Wishlist",
  reviews: "Reviews",
  new_checkout: "New checkout UI",
};
