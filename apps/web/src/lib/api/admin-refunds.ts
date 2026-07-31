import { hasAdminPermission } from "@/lib/api/admin-me";

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

export type AdminRefundRecord = {
  id: string;
  return_request_id?: string | null;
  order_id?: string;
  customer_id?: string | null;
  payment_id?: string | null;
  amount: number | string;
  currency?: string;
  status: string;
  status_label?: string | null;
  method?: string | null;
  reference?: string | null;
  provider_reference?: string | null;
  notes?: string | null;
  reason?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  order?: {
    id?: string;
    order_number?: string | null;
    status?: string;
    total?: number | string | null;
    currency?: string | null;
    customer?: { id: string; name: string; email: string } | null;
  } | null;
  customer?: { id: string; name: string; email: string } | null;
  payment?: {
    id: string;
    amount?: number | string;
    status?: string;
    method?: string | null;
  } | null;
};

export class AdminRefundsApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "AdminRefundsApiError";
  }
}

async function parseJsonResponse<T>(response: Response): Promise<T> {
  try {
    return (await response.json()) as T;
  } catch {
    throw new AdminRefundsApiError("Invalid server response.", response.status);
  }
}

export type AdminRefundListParams = {
  status?: string;
  orderId?: string;
  customerId?: string;
  paymentId?: string;
  search?: string;
  page?: number;
  perPage?: number;
};

export async function fetchAdminRefunds(
  params: AdminRefundListParams = {},
): Promise<{ data: AdminRefundRecord[]; meta: ApiSuccessResponse<AdminRefundRecord[]>["meta"] }> {
  const searchParams = new URLSearchParams();
  if (params.status) searchParams.set("status", params.status);
  if (params.orderId) searchParams.set("order_id", params.orderId);
  if (params.customerId) searchParams.set("customer_id", params.customerId);
  if (params.paymentId) searchParams.set("payment_id", params.paymentId);
  if (params.search) searchParams.set("search", params.search);
  if (params.page) searchParams.set("page", String(params.page));
  if (params.perPage) searchParams.set("per_page", String(params.perPage));

  const response = await fetch(`/api/admin/refunds?${searchParams.toString()}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<ApiSuccessResponse<AdminRefundRecord[]>>(response);
  if (!response.ok || payload.success === false) {
    throw new AdminRefundsApiError(
      payload.message?.trim() || "Unable to load refunds.",
      response.status,
      payload.errors,
    );
  }

  return { data: payload.data ?? [], meta: payload.meta };
}

export async function fetchAdminRefund(id: string): Promise<AdminRefundRecord> {
  const response = await fetch(`/api/admin/refunds/${encodeURIComponent(id)}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await parseJsonResponse<ApiSuccessResponse<AdminRefundRecord>>(response);
  if (!response.ok || payload.success === false || !payload.data) {
    throw new AdminRefundsApiError(
      payload.message?.trim() || "Unable to load refund.",
      response.status,
      payload.errors,
    );
  }

  return payload.data;
}

export async function approveAdminRefund(id: string, notes?: string): Promise<AdminRefundRecord> {
  const response = await fetch(`/api/admin/refunds/${encodeURIComponent(id)}/approve`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ notes: notes ?? null }),
  });

  const payload = await parseJsonResponse<ApiSuccessResponse<AdminRefundRecord>>(response);
  if (!response.ok || payload.success === false || !payload.data) {
    throw new AdminRefundsApiError(
      payload.message?.trim() || "Unable to approve refund.",
      response.status,
      payload.errors,
    );
  }

  return payload.data;
}

export async function rejectAdminRefund(id: string, reason: string): Promise<AdminRefundRecord> {
  const response = await fetch(`/api/admin/refunds/${encodeURIComponent(id)}/reject`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ reason }),
  });

  const payload = await parseJsonResponse<ApiSuccessResponse<AdminRefundRecord>>(response);
  if (!response.ok || payload.success === false || !payload.data) {
    throw new AdminRefundsApiError(
      payload.message?.trim() || "Unable to reject refund.",
      response.status,
      payload.errors,
    );
  }

  return payload.data;
}

export async function processAdminRefund(id: string, notes?: string): Promise<AdminRefundRecord> {
  const response = await fetch(`/api/admin/refunds/${encodeURIComponent(id)}/process`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ notes: notes ?? null }),
  });

  const payload = await parseJsonResponse<ApiSuccessResponse<AdminRefundRecord>>(response);
  if (!response.ok || payload.success === false || !payload.data) {
    throw new AdminRefundsApiError(
      payload.message?.trim() || "Unable to process refund.",
      response.status,
      payload.errors,
    );
  }

  return payload.data;
}

export function canViewAdminRefunds(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "refunds.view");
}

export function canManageAdminRefunds(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "refunds.manage");
}

export function canApproveAdminRefunds(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "refunds.approve");
}

export function refundStatusLabel(status: string): string {
  switch (status) {
    case "requested":
      return "Requested";
    case "under_review":
      return "Under review";
    case "approved":
      return "Approved";
    case "processing":
      return "Processing";
    case "completed":
      return "Completed";
    case "failed":
      return "Failed";
    case "rejected":
      return "Rejected";
    case "pending":
      return "Pending";
    default:
      return status;
  }
}

export function refundStatusBadgeClass(status: string): string {
  switch (status) {
    case "completed":
      return "bg-emerald-50 text-emerald-700";
    case "failed":
    case "rejected":
      return "bg-red-50 text-red-700";
    case "approved":
    case "processing":
      return "bg-sky-50 text-sky-700";
    default:
      return "bg-amber-50 text-amber-800";
  }
}
