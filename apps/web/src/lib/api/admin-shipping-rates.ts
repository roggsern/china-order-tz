import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminShippingRatesApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminShippingRatesApiError";
  }
}

export type AdminShippingRate = {
  method: string;
  method_name?: string;
  price: number;
  currency?: string;
  estimated_min_days: number | null;
  estimated_max_days: number | null;
  estimated_delivery_days: number | null;
  active: boolean;
  shipping_rate_id?: string | null;
};

export type UpdateAdminShippingRateInput = {
  price?: number;
  estimated_min_days?: number;
  estimated_max_days?: number;
  estimated_delivery_days?: number;
  active?: boolean;
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
  throw new AdminShippingRatesApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export function canViewShippingRates(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "shipping.view");
}

export function canManageShippingRates(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "shipping.manage");
}

export async function fetchAdminShippingRates(): Promise<AdminShippingRate[]> {
  const response = await fetch("/api/admin/shipping/rates", {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminShippingRate[];
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load shipping rates.");
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

export async function updateAdminShippingRate(
  method: string,
  input: UpdateAdminShippingRateInput,
): Promise<AdminShippingRate> {
  const response = await fetch(`/api/admin/shipping/rates/${encodeURIComponent(method)}`, {
    method: "PUT",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminShippingRate;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to update shipping rate.");
  }

  if (!payload.data) {
    throw new AdminShippingRatesApiError("Invalid shipping rate response.", response.status);
  }

  return payload.data;
}

export const SHIPPING_METHOD_LABELS: Record<string, string> = {
  air_freight: "Air Freight",
  sea_freight: "Sea Freight",
  local_delivery: "Local Delivery",
};
