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

export type AdminNotificationLogRow = {
  id: string;
  customer_id?: string | null;
  admin_id?: string | null;
  event_type?: string | null;
  template_key?: string | null;
  title: string;
  message: string;
  channel?: string | null;
  status?: string | null;
  provider?: string | null;
  provider_message_id?: string | null;
  error_message?: string | null;
  sent_at?: string | null;
  read_at?: string | null;
  created_at?: string | null;
  customer?: { id: string; name: string; email: string } | null;
};

export type AdminNotificationTemplate = {
  id: string;
  key: string;
  name: string;
  channel: string;
  subject?: string | null;
  body: string;
  is_active: boolean;
  created_at?: string | null;
  updated_at?: string | null;
};

export class AdminNotificationsApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminNotificationsApiError";
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
): Promise<{ data: T; meta?: ApiSuccessResponse<T>["meta"]; raw: ApiSuccessResponse<T> }> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new AdminNotificationsApiError(formatError(payload, fallback), response.status);
  }
  return { data: payload.data as T, meta: payload.meta, raw: payload };
}

export async function fetchAdminNotificationLog(params?: {
  channel?: string;
  status?: string;
  eventType?: string;
  page?: number;
}): Promise<AdminNotificationLogRow[]> {
  const search = new URLSearchParams();
  if (params?.channel) search.set("channel", params.channel);
  if (params?.status) search.set("status", params.status);
  if (params?.eventType) search.set("event_type", params.eventType);
  if (params?.page) search.set("page", String(params.page));
  search.set("per_page", "50");

  const qs = search.toString();
  const { data } = await adminFetch<AdminNotificationLogRow[]>(
    `/api/admin/notifications${qs ? `?${qs}` : ""}`,
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load notification log.",
  );

  return Array.isArray(data) ? data : [];
}

export async function fetchAdminNotificationTemplates(): Promise<AdminNotificationTemplate[]> {
  const { data } = await adminFetch<AdminNotificationTemplate[]>(
    "/api/admin/notification-templates",
    { method: "GET", headers: { Accept: "application/json" } },
    "Unable to load templates.",
  );
  return Array.isArray(data) ? data : [];
}

export async function updateAdminNotificationTemplate(
  id: string,
  body: Partial<{
    name: string;
    subject: string | null;
    body: string;
    is_active: boolean;
    channel: string;
  }>,
): Promise<AdminNotificationTemplate> {
  const { data } = await adminFetch<AdminNotificationTemplate>(
    `/api/admin/notification-templates/${encodeURIComponent(id)}`,
    {
      method: "PUT",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to update template.",
  );
  return data;
}

export async function previewAdminNotificationTemplate(
  id: string,
  variables: Record<string, string>,
): Promise<{ subject: string | null; body: string; title: string }> {
  const { data } = await adminFetch<{
    template: AdminNotificationTemplate;
    rendered: { subject: string | null; body: string; title: string };
  }>(
    `/api/admin/notification-templates/${encodeURIComponent(id)}/preview`,
    {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ variables }),
    },
    "Unable to preview template.",
  );
  return data.rendered;
}
