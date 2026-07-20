import type { AdminFulfillmentApiError } from "@/lib/api/admin-fulfillments";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type AdminRefundPendingOrder = {
  id: string;
  order_number: string;
  status: string;
  status_label?: string;
  customer_status_label?: string;
  total?: string | number;
  grand_total?: string | number;
  currency?: string;
  paid_at?: string | null;
  cancelled_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  user?: {
    id?: string;
    name?: string;
    email?: string;
    phone?: string | null;
  } | null;
  payments?: Array<{
    method?: string;
    amount?: string | number;
    status?: string;
    reference?: string | null;
  }>;
  refund_transactions?: Array<{
    id: string;
    amount: string | number;
    status: string;
    method?: string | null;
    reference?: string | null;
    notes?: string | null;
    created_at?: string | null;
  }>;
  status_history?: Array<{
    new_status?: string;
    previous_status?: string | null;
    source?: string | null;
    notes?: string | null;
    created_at?: string | null;
  }>;
};

export class AdminOrdersApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminOrdersApiError";
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
    throw new AdminOrdersApiError(formatError(payload, fallback), response.status);
  }
  return (payload.data ?? payload) as T;
}

export async function fetchRefundPendingOrders(): Promise<AdminRefundPendingOrder[]> {
  const response = await fetch("/api/admin/orders?status=refund_pending&per_page=50", {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = (await response.json()) as {
    success?: boolean;
    orders?: AdminRefundPendingOrder[];
    data?: AdminRefundPendingOrder[] | { data?: AdminRefundPendingOrder[] };
    message?: string;
  };

  if (!response.ok) {
    throw new AdminOrdersApiError(payload.message || "Unable to load refund queue.", response.status);
  }

  if (Array.isArray(payload.orders)) {
    return payload.orders as AdminRefundPendingOrder[];
  }

  if (Array.isArray(payload.data)) {
    return payload.data;
  }

  if (payload.data && typeof payload.data === "object" && Array.isArray(payload.data.data)) {
    return payload.data.data;
  }

  return [];
}

export async function completeCancellationRefund(
  orderId: string,
  input: {
    amount: number | string;
    reference: string;
    notes?: string;
    reason?: string;
    confirm: true;
  },
): Promise<unknown> {
  return adminFetch(
    `/api/admin/orders/${encodeURIComponent(orderId)}/refunds/complete`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(input),
    },
    "Unable to complete cancellation refund.",
  );
}

export async function failCancellationRefund(
  orderId: string,
  input?: { notes?: string; reason?: string },
): Promise<unknown> {
  return adminFetch(
    `/api/admin/orders/${encodeURIComponent(orderId)}/refunds/fail`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(input ?? {}),
    },
    "Unable to mark cancellation refund failed.",
  );
}

export type { AdminFulfillmentApiError };
