export class AdminCrmApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminCrmApiError";
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
  throw new AdminCrmApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export type AdminCustomerMetrics = {
  total_orders: number;
  completed_orders: number;
  cancelled_orders: number;
  total_spend: string;
  total_refunds: string;
  gross_profit_generated: string;
  average_order_value: string;
  first_order_at?: string | null;
  last_order_at?: string | null;
  last_payment_at?: string | null;
  currency: string;
};

export type AdminCustomerTag = {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  is_active: boolean;
};

export type AdminCustomerLoyalty = {
  id: string;
  loyalty_number: string;
  status: string;
  points_balance: number;
  lifetime_points: number;
  lifetime_redeemed: number;
  tier?: { id: string; code: string; name: string; earn_multiplier?: string | number } | null;
  enrolled_at?: string | null;
};

export type AdminCustomer = {
  id: string;
  customer_code: string;
  user_id: string;
  name?: string | null;
  email?: string | null;
  phone?: string | null;
  registration_source: string;
  lifecycle_status: string;
  block_reason?: string | null;
  marketing_opt_in?: boolean;
  registered_at?: string;
  created_at?: string;
  metrics?: AdminCustomerMetrics | null;
  tags?: AdminCustomerTag[];
  loyalty?: AdminCustomerLoyalty | null;
};

export type AdminCustomerSummary = {
  total_customers: number;
  new_customers_today: number;
  new_customers_this_month: number;
  active_customers: number;
  dormant_customers: number;
  blocked_customers: number;
  customers_with_purchases: number;
  total_lifetime_spend: string;
  currency: string;
};

export type AdminCustomerNote = {
  id: string;
  body: string;
  is_pinned: boolean;
  author?: { id: string; name?: string | null } | null;
  created_at?: string;
};

export type AdminCustomerTimelineEvent = {
  id: string;
  event_type: string;
  title: string;
  description?: string | null;
  occurred_at: string;
};

export type CustomerListQuery = {
  search?: string;
  lifecycle_status?: string;
  registration_source?: string;
  tag?: string;
  sort?: string;
  direction?: string;
  page?: number;
  per_page?: number;
  no_orders?: boolean;
  dormant?: boolean;
  blocked?: boolean;
  min_spend?: string;
  max_spend?: string;
};

function toParams(query?: CustomerListQuery): URLSearchParams {
  const params = new URLSearchParams();
  if (!query) return params;
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === "" || value === false) continue;
    params.set(key, String(value));
  }
  return params;
}

export async function fetchAdminCustomerSummary(): Promise<AdminCustomerSummary> {
  const response = await fetch("/api/admin/customers/summary", {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomerSummary; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to load customer summary.");
  }
  return payload.data;
}

export async function fetchAdminCustomers(query?: CustomerListQuery): Promise<{
  data: AdminCustomer[];
  meta?: { current_page?: number; last_page?: number; total?: number };
}> {
  const params = toParams(query);
  const qs = params.toString();
  const response = await fetch(`/api/admin/customers${qs ? `?${qs}` : ""}`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    data?: AdminCustomer[];
    meta?: { current_page?: number; last_page?: number; total?: number };
    message?: string;
    errors?: Record<string, string[]>;
  }>(response);
  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load customers.");
  }
  return { data: Array.isArray(payload.data) ? payload.data : [], meta: payload.meta };
}

export async function fetchAdminCustomer(id: string): Promise<AdminCustomer> {
  const response = await fetch(`/api/admin/customers/${id}`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomer; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to load customer.");
  }
  return payload.data;
}

export async function updateAdminCustomerStatus(
  id: string,
  body: { lifecycle_status: string; block_reason?: string },
): Promise<AdminCustomer> {
  const response = await fetch(`/api/admin/customers/${id}/status`, {
    method: "PATCH",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomer; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to update status.");
  }
  return payload.data;
}

export async function fetchAdminCustomerTags(): Promise<AdminCustomerTag[]> {
  const response = await fetch("/api/admin/customer-tags?active_only=1", {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomerTag[]; message?: string }>(
    response,
  );
  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load tags.");
  }
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function assignAdminCustomerTag(customerId: string, tagId: string): Promise<AdminCustomer> {
  const response = await fetch(`/api/admin/customers/${customerId}/tags`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ tag_id: tagId }),
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomer; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to assign tag.");
  }
  return payload.data;
}

export async function removeAdminCustomerTag(customerId: string, tagId: string): Promise<AdminCustomer> {
  const response = await fetch(`/api/admin/customers/${customerId}/tags/${tagId}`, {
    method: "DELETE",
    credentials: "include",
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomer; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to remove tag.");
  }
  return payload.data;
}

export async function fetchAdminCustomerNotes(customerId: string): Promise<AdminCustomerNote[]> {
  const response = await fetch(`/api/admin/customers/${customerId}/notes`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomerNote[]; message?: string }>(
    response,
  );
  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load notes.");
  }
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createAdminCustomerNote(
  customerId: string,
  body: string,
  isPinned = false,
): Promise<AdminCustomerNote> {
  const response = await fetch(`/api/admin/customers/${customerId}/notes`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ body, is_pinned: isPinned }),
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomerNote; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to add note.");
  }
  return payload.data;
}

export async function deleteAdminCustomerNote(customerId: string, noteId: string): Promise<void> {
  const response = await fetch(`/api/admin/customers/${customerId}/notes/${noteId}`, {
    method: "DELETE",
    credentials: "include",
  });
  const payload = await parseJson<{ success?: boolean; message?: string }>(response);
  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to delete note.");
  }
}

export async function fetchAdminCustomerTimeline(
  customerId: string,
): Promise<AdminCustomerTimelineEvent[]> {
  const response = await fetch(`/api/admin/customers/${customerId}/timeline`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    data?: AdminCustomerTimelineEvent[];
    message?: string;
  }>(response);
  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load timeline.");
  }
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchAdminCustomerRelated(
  customerId: string,
  resource: "orders" | "payments" | "shipments" | "returns" | "addresses",
): Promise<unknown> {
  const response = await fetch(`/api/admin/customers/${customerId}/${resource}`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{ success?: boolean; data?: unknown; message?: string }>(response);
  if (!response.ok) {
    throwFromPayload(response, payload, `Unable to load ${resource}.`);
  }
  return payload.data;
}

export async function rebuildAdminCustomerMetrics(customerId: string): Promise<AdminCustomer> {
  const response = await fetch(`/api/admin/customers/${customerId}/metrics/rebuild`, {
    method: "POST",
    credentials: "include",
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomer; message?: string }>(
    response,
  );
  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to rebuild metrics.");
  }
  return payload.data;
}
