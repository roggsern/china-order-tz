type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
  errors?: Record<string, string[]>;
};

export type AdminFulfillment = {
  id: string;
  order_id: string;
  strategy: string;
  strategy_label?: string | null;
  status: string;
  status_label?: string | null;
  assigned_to?: string | null;
  assignee?: {
    id: string;
    name: string;
    email: string;
  } | null;
  started_at?: string | null;
  completed_at?: string | null;
  notes?: string | null;
  order?: {
    id: string;
    order_number: string;
    status?: string;
    total?: string | number;
    currency?: string;
    paid_at?: string | null;
    customer?: {
      id: string;
      name: string;
      email: string;
      phone?: string | null;
    } | null;
  } | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export class AdminFulfillmentApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminFulfillmentApiError";
  }
}

function formatError(payload: ApiSuccessResponse<unknown>, fallback: string): string {
  if (payload.message?.trim()) return payload.message.trim();
  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) return first.trim();
  }
  return fallback;
}

async function adminFetch<T>(
  path: string,
  init: RequestInit,
  fallback: string,
): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new AdminFulfillmentApiError(formatError(payload, fallback), response.status);
  }
  return payload.data as T;
}

export async function fetchAdminFulfillments(params?: {
  strategy?: string;
  status?: string;
  orderId?: string;
  page?: number;
}): Promise<AdminFulfillment[]> {
  const search = new URLSearchParams();
  if (params?.strategy) search.set("strategy", params.strategy);
  if (params?.status) search.set("status", params.status);
  if (params?.orderId) search.set("order_id", params.orderId);
  if (params?.page) search.set("page", String(params.page));
  search.set("per_page", "50");

  const qs = search.toString();
  const data = await adminFetch<AdminFulfillment[] | { data: AdminFulfillment[] }>(
    `/api/admin/fulfillments${qs ? `?${qs}` : ""}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load fulfillments.",
  );

  if (Array.isArray(data)) return data;
  return [];
}

export async function fetchAdminFulfillment(id: string): Promise<AdminFulfillment> {
  return adminFetch<AdminFulfillment>(
    `/api/admin/fulfillments/${encodeURIComponent(id)}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load fulfillment.",
  );
}

export async function createAdminFulfillment(orderId: string): Promise<AdminFulfillment> {
  return adminFetch<AdminFulfillment>(
    `/api/admin/fulfillments/create/${encodeURIComponent(orderId)}`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    },
    "Unable to create fulfillment.",
  );
}

export async function updateAdminFulfillmentStatus(
  id: string,
  body: { status?: string; assigned_to?: string | null; notes?: string | null },
): Promise<AdminFulfillment> {
  return adminFetch<AdminFulfillment>(
    `/api/admin/fulfillments/${encodeURIComponent(id)}/status`,
    {
      method: "PATCH",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(body),
    },
    "Unable to update fulfillment status.",
  );
}
