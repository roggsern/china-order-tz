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

export type AdminFulfillmentProduct = {
  name: string;
  variant_label?: string | null;
  quantity: number;
  image_url?: string | null;
  additional_item_count?: number;
};

export type AdminFulfillmentChinaSummary = {
  stage?: string | null;
  qc_status?: string | null;
  export_ready?: boolean;
  has_supplier_purchase?: boolean;
  purchase_receivable?: boolean;
  supplier_purchase_state?: string | null;
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
  warehouse_status?: string | null;
  shipment_status?: string | null;
  shipment_arrived_at?: string | null;
  china?: AdminFulfillmentChinaSummary | null;
  order?: {
    id: string;
    order_number: string;
    status?: string;
    source?: string;
    journey?: string;
    total?: string | number;
    currency?: string;
    paid_at?: string | null;
    delivery_type?: string | null;
    last_mile_receiving_method?: string | null;
    product?: AdminFulfillmentProduct | null;
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

export type AdminFulfillmentListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type AdminFulfillmentListPage = {
  items: AdminFulfillment[];
  meta: AdminFulfillmentListMeta;
};

async function adminFetchPayload<T>(
  path: string,
  init: RequestInit,
  fallback: string,
): Promise<ApiSuccessResponse<T>> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new AdminFulfillmentApiError(formatError(payload, fallback), response.status);
  }
  return payload;
}

async function adminFetch<T>(
  path: string,
  init: RequestInit,
  fallback: string,
): Promise<T> {
  const payload = await adminFetchPayload<T>(path, init, fallback);
  return payload.data as T;
}

function normalizeFulfillmentListMeta(
  payload: ApiSuccessResponse<unknown>,
  fallbackPage: number,
  fallbackPerPage: number,
): AdminFulfillmentListMeta {
  const meta = payload.meta ?? {};
  const items = Array.isArray(payload.data) ? payload.data : [];
  const perPage = meta.per_page ?? fallbackPerPage;
  const total = meta.total ?? items.length;
  const lastPage = meta.last_page ?? Math.max(1, Math.ceil(total / perPage));

  return {
    current_page: meta.current_page ?? fallbackPage,
    last_page: lastPage,
    per_page: perPage,
    total,
  };
}

export async function fetchAdminFulfillmentsPage(params?: {
  strategy?: string;
  status?: string;
  orderId?: string;
  page?: number;
  perPage?: number;
}): Promise<AdminFulfillmentListPage> {
  const page = params?.page && params.page > 0 ? params.page : 1;
  const perPage =
    params?.perPage && params.perPage > 0 ? params.perPage : 20;

  const search = new URLSearchParams();
  if (params?.strategy) search.set("strategy", params.strategy);
  if (params?.status) search.set("status", params.status);
  if (params?.orderId) search.set("order_id", params.orderId);
  search.set("page", String(page));
  search.set("per_page", String(perPage));

  const qs = search.toString();
  const payload = await adminFetchPayload<AdminFulfillment[]>(
    `/api/admin/fulfillments${qs ? `?${qs}` : ""}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load fulfillments.",
  );

  const items = Array.isArray(payload.data) ? payload.data : [];

  return {
    items,
    meta: normalizeFulfillmentListMeta(payload, page, perPage),
  };
}

export async function fetchAdminFulfillments(params?: {
  strategy?: string;
  status?: string;
  orderId?: string;
  page?: number;
}): Promise<AdminFulfillment[]> {
  const page = await fetchAdminFulfillmentsPage(params);
  return page.items;
}

export async function fetchAdminFulfillment(id: string): Promise<AdminFulfillment> {
  return adminFetch<AdminFulfillment>(
    `/api/admin/fulfillments/${encodeURIComponent(id)}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load fulfillment.",
  );
}

export async function fetchAdminFulfillmentOperational(id: string): Promise<unknown> {
  return adminFetch<unknown>(
    `/api/admin/fulfillments/${encodeURIComponent(id)}/operational`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load fulfillment operational view.",
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
