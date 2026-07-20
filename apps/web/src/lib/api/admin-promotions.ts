export class AdminPromotionsApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminPromotionsApiError";
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
  throw new AdminPromotionsApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export type AdminPromotionRule = {
  id?: string;
  rule_type: string;
  rule_value: Record<string, unknown>;
};

export type AdminPromotion = {
  id: string;
  name: string;
  code?: string | null;
  type: string;
  discount_type: string;
  value: string | number;
  currency?: string | null;
  status: string;
  starts_at?: string | null;
  ends_at?: string | null;
  usage_limit?: number | null;
  per_customer_limit?: number | null;
  minimum_order_amount?: string | number | null;
  rules?: AdminPromotionRule[];
  usages_count?: number;
};

export async function fetchAdminPromotions(params?: {
  search?: string;
  status?: string;
}): Promise<AdminPromotion[]> {
  const qs = new URLSearchParams();
  if (params?.search) qs.set("search", params.search);
  if (params?.status) qs.set("status", params.status);
  const response = await fetch(`/api/admin/promotions${qs.toString() ? `?${qs}` : ""}`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminPromotion[]; message?: string }>(
    response,
  );
  if (!response.ok) throwFromPayload(response, payload, "Unable to load promotions.");
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createAdminPromotion(body: Record<string, unknown>): Promise<AdminPromotion> {
  const response = await fetch("/api/admin/promotions", {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminPromotion; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to create promotion.");
  }
  return payload.data;
}

export async function updateAdminPromotionStatus(
  id: string,
  status: string,
): Promise<AdminPromotion> {
  const response = await fetch(`/api/admin/promotions/${id}/status`, {
    method: "PATCH",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ status }),
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminPromotion; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to update status.");
  }
  return payload.data;
}

export async function fetchAdminPromotionUsage(id: string): Promise<unknown[]> {
  const response = await fetch(`/api/admin/promotions/${id}/usage`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{ success?: boolean; data?: unknown[]; message?: string }>(
    response,
  );
  if (!response.ok) throwFromPayload(response, payload, "Unable to load usage.");
  return Array.isArray(payload.data) ? payload.data : [];
}
