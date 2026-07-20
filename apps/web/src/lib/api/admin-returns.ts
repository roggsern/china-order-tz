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

export type AdminReturnItem = {
  id: string;
  return_request_id?: string;
  order_item_id: string;
  quantity: number;
  reason?: string | null;
  condition?: string | null;
  resolution?: string | null;
  refund_amount?: number | string | null;
  replacement_requested?: boolean;
  order_item?: {
    id?: string;
    product_name?: string | null;
    quantity?: number;
    unit_price?: number | string | null;
  } | null;
};

export type AdminRefundTransaction = {
  id: string;
  return_request_id?: string;
  order_id?: string;
  amount: number | string;
  currency?: string;
  status: string;
  status_label?: string | null;
  method?: string | null;
  reference?: string | null;
  notes?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type AdminReturnRequest = {
  id: string;
  order_id: string;
  customer_id: string;
  status: string;
  status_label?: string | null;
  reason: string;
  description?: string | null;
  customer_notes?: string | null;
  admin_notes?: string | null;
  approved_by?: string | null;
  approved_at?: string | null;
  completed_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  order?: {
    id: string;
    order_number: string;
    status?: string;
    total?: number | string;
    currency?: string;
  } | null;
  customer?: {
    id: string;
    name: string;
    email: string;
  } | null;
  approver?: {
    id: string;
    name: string;
    email: string;
  } | null;
  items?: AdminReturnItem[];
  refund_transactions?: AdminRefundTransaction[];
  latest_refund?: AdminRefundTransaction | null;
};

export class AdminReturnsApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminReturnsApiError";
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

async function adminFetch<T>(path: string, init: RequestInit, fallback: string): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new AdminReturnsApiError(formatError(payload, fallback), response.status);
  }
  return payload.data as T;
}

function unwrapList(data: unknown): AdminReturnRequest[] {
  if (Array.isArray(data)) return data as AdminReturnRequest[];
  if (data && typeof data === "object" && Array.isArray((data as { data: unknown }).data)) {
    return (data as { data: AdminReturnRequest[] }).data;
  }
  return [];
}

export async function fetchAdminReturns(params?: {
  status?: string;
  orderId?: string;
  customerId?: string;
  page?: number;
}): Promise<AdminReturnRequest[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set("status", params.status);
  if (params?.orderId) search.set("order_id", params.orderId);
  if (params?.customerId) search.set("customer_id", params.customerId);
  if (params?.page) search.set("page", String(params.page));
  search.set("per_page", "50");

  const qs = search.toString();
  const data = await adminFetch<AdminReturnRequest[] | { data: AdminReturnRequest[] }>(
    `/api/admin/returns${qs ? `?${qs}` : ""}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load returns.",
  );

  return unwrapList(data);
}

export async function fetchAdminReturn(returnId: string): Promise<AdminReturnRequest> {
  return adminFetch<AdminReturnRequest>(
    `/api/admin/returns/${encodeURIComponent(returnId)}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load return request.",
  );
}

export async function updateAdminReturnStatus(
  returnId: string,
  body: {
    status: string;
    admin_notes?: string | null;
    items?: Array<{
      id: string;
      condition?: string | null;
      resolution?: string | null;
      refund_amount?: number | null;
    }>;
  },
): Promise<AdminReturnRequest> {
  return adminFetch<AdminReturnRequest>(
    `/api/admin/returns/${encodeURIComponent(returnId)}/status`,
    {
      method: "PATCH",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to update return status.",
  );
}

export async function createAdminReturnRefund(
  returnId: string,
  body: {
    amount?: number;
    currency?: string;
    method?: string;
    reference?: string | null;
    notes?: string | null;
    status?: "pending" | "approved" | "processing" | "completed" | "failed";
  },
): Promise<AdminRefundTransaction> {
  return adminFetch<AdminRefundTransaction>(
    `/api/admin/returns/${encodeURIComponent(returnId)}/refund`,
    {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to create refund.",
  );
}
