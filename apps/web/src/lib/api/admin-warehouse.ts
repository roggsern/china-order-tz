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

export type AdminWarehouseJob = {
  id: string;
  job_number: string;
  order_id: string;
  fulfillment_id: string;
  status: string;
  status_label?: string | null;
  next_status?: string | null;
  picker_id?: string | null;
  packer_id?: string | null;
  picker?: { id: string; name: string; email: string } | null;
  packer?: { id: string; name: string; email: string } | null;
  picked_at?: string | null;
  packed_at?: string | null;
  ready_at?: string | null;
  notes?: string | null;
  order?: {
    id: string;
    order_number: string;
    status?: string;
    customer?: { id: string; name: string; email: string } | null;
  } | null;
  fulfillment?: {
    id: string;
    status?: string;
    strategy?: string;
  } | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export class AdminWarehouseApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminWarehouseApiError";
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
    throw new AdminWarehouseApiError(formatError(payload, fallback), response.status);
  }
  return payload.data as T;
}

export async function fetchAdminWarehouseJobs(params?: {
  status?: string;
  orderId?: string;
  page?: number;
}): Promise<AdminWarehouseJob[]> {
  const search = new URLSearchParams();
  if (params?.status) search.set("status", params.status);
  if (params?.orderId) search.set("order_id", params.orderId);
  if (params?.page) search.set("page", String(params.page));
  search.set("per_page", "50");

  const qs = search.toString();
  const data = await adminFetch<AdminWarehouseJob[] | { data: AdminWarehouseJob[] }>(
    `/api/admin/warehouse${qs ? `?${qs}` : ""}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load warehouse queue.",
  );

  if (Array.isArray(data)) return data;
  if (data && Array.isArray((data as { data: AdminWarehouseJob[] }).data)) {
    return (data as { data: AdminWarehouseJob[] }).data;
  }
  return [];
}

export async function updateAdminWarehouseStatus(
  jobId: string,
  body: { status: string; notes?: string | null },
): Promise<AdminWarehouseJob> {
  return adminFetch<AdminWarehouseJob>(
    `/api/admin/warehouse/${encodeURIComponent(jobId)}/status`,
    {
      method: "PATCH",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to update warehouse status.",
  );
}

export async function assignAdminWarehousePicker(
  jobId: string,
  pickerId?: string | null,
): Promise<AdminWarehouseJob> {
  const body =
    pickerId === undefined ? {} : { picker_id: pickerId };

  return adminFetch<AdminWarehouseJob>(
    `/api/admin/warehouse/${encodeURIComponent(jobId)}/assign-picker`,
    {
      method: "PATCH",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to assign picker.",
  );
}

export async function assignAdminWarehousePacker(
  jobId: string,
  packerId?: string | null,
): Promise<AdminWarehouseJob> {
  const body =
    packerId === undefined ? {} : { packer_id: packerId };

  return adminFetch<AdminWarehouseJob>(
    `/api/admin/warehouse/${encodeURIComponent(jobId)}/assign-packer`,
    {
      method: "PATCH",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to assign packer.",
  );
}
