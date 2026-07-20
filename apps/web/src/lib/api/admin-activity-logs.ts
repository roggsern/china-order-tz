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

export type ActivityChange = {
  field: string;
  old: unknown;
  new: unknown;
};

export type AdminActivityLog = {
  id: string;
  event_type: string;
  event_type_label?: string | null;
  action: string;
  actor_type: string;
  actor_id?: string | null;
  actor?: { id: string; name?: string | null; email?: string | null } | null;
  subject_type?: string | null;
  subject_id?: string | null;
  description: string;
  old_values?: Record<string, unknown> | null;
  new_values?: Record<string, unknown> | null;
  changes?: ActivityChange[];
  metadata?: Record<string, unknown> | null;
  ip_address?: string | null;
  user_agent?: string | null;
  created_at?: string | null;
};

export class AdminActivityLogsApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminActivityLogsApiError";
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
): Promise<{ data: T; meta?: ApiSuccessResponse<T>["meta"] }> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new AdminActivityLogsApiError(formatError(payload, fallback), response.status);
  }
  return { data: payload.data as T, meta: payload.meta };
}

export async function fetchAdminActivityLogs(params?: {
  eventType?: string;
  actorType?: string;
  search?: string;
  dateFrom?: string;
  dateTo?: string;
  page?: number;
}): Promise<{ rows: AdminActivityLog[]; meta?: ApiSuccessResponse<unknown>["meta"] }> {
  const search = new URLSearchParams();
  if (params?.eventType) search.set("event_type", params.eventType);
  if (params?.actorType) search.set("actor_type", params.actorType);
  if (params?.search) search.set("search", params.search);
  if (params?.dateFrom) search.set("date_from", params.dateFrom);
  if (params?.dateTo) search.set("date_to", params.dateTo);
  if (params?.page) search.set("page", String(params.page));
  search.set("per_page", "40");

  const qs = search.toString();
  const { data, meta } = await adminFetch<AdminActivityLog[]>(
    `/api/admin/activity-logs${qs ? `?${qs}` : ""}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load activity logs.",
  );

  return { rows: Array.isArray(data) ? data : [], meta };
}

export async function fetchAdminActivityLog(
  id: string,
): Promise<AdminActivityLog> {
  const { data } = await adminFetch<AdminActivityLog>(
    `/api/admin/activity-logs/${encodeURIComponent(id)}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load activity log.",
  );
  return data;
}
