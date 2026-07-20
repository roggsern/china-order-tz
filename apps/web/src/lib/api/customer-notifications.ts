import { getCustomerApiToken } from "@/lib/api/customer-auth";

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

export type CustomerNotification = {
  id: string;
  type: string;
  event_type?: string | null;
  title: string;
  message: string;
  channel?: string | null;
  status?: string | null;
  data?: Record<string, unknown> | null;
  is_read: boolean;
  read_at?: string | null;
  sent_at?: string | null;
  created_at?: string | null;
};

export class CustomerNotificationsApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerNotificationsApiError";
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

async function customerFetch<T>(
  path: string,
  init: RequestInit,
  fallback: string,
): Promise<{ data: T; meta?: ApiSuccessResponse<T>["meta"]; raw: ApiSuccessResponse<T> }> {
  const authToken = getCustomerApiToken();
  if (!authToken) {
    throw new CustomerNotificationsApiError("Sign in to view notifications.", 401);
  }

  const response = await fetch(path, {
    ...init,
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
      ...(init.headers ?? {}),
    },
    cache: "no-store",
  });

  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new CustomerNotificationsApiError(formatError(payload, fallback), response.status);
  }

  return { data: payload.data as T, meta: payload.meta, raw: payload };
}

export async function fetchCustomerNotifications(params?: {
  page?: number;
  perPage?: number;
}): Promise<{ notifications: CustomerNotification[]; meta?: ApiSuccessResponse<unknown>["meta"] }> {
  const search = new URLSearchParams();
  if (params?.page) search.set("page", String(params.page));
  search.set("per_page", String(params?.perPage ?? 20));

  const response = await fetch(`/api/notifications/inbox?${search.toString()}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${getCustomerApiToken() ?? ""}`,
    },
    cache: "no-store",
  });

  const payload = (await response.json()) as ApiSuccessResponse<CustomerNotification[]> & {
    data?: CustomerNotification[];
  };

  if (!response.ok || payload.success === false) {
    throw new CustomerNotificationsApiError(
      formatError(payload, "Unable to load notifications."),
      response.status,
    );
  }

  const list = Array.isArray(payload.data) ? payload.data : [];
  return { notifications: list, meta: payload.meta };
}

export async function fetchCustomerUnreadCount(): Promise<number> {
  const { data } = await customerFetch<{ unread_count: number }>(
    "/api/notifications/inbox/unread-count",
    { method: "GET" },
    "Unable to load unread count.",
  );
  return data?.unread_count ?? 0;
}

export async function markCustomerNotificationRead(
  id: string,
): Promise<CustomerNotification> {
  const { data } = await customerFetch<CustomerNotification>(
    `/api/notifications/inbox/${encodeURIComponent(id)}/read`,
    { method: "PATCH" },
    "Unable to mark notification as read.",
  );
  return data;
}

export async function markAllCustomerNotificationsRead(): Promise<number> {
  const { data } = await customerFetch<{ marked: number }>(
    "/api/notifications/inbox/read-all",
    { method: "POST" },
    "Unable to mark all notifications as read.",
  );
  return data?.marked ?? 0;
}
