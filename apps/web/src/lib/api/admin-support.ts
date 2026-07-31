import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminSupportApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminSupportApiError";
  }
}

export type SupportTicketRecord = {
  id: string;
  ticket_number: string;
  subject: string;
  category: string;
  category_label: string;
  priority: string;
  priority_label: string;
  status: string;
  status_label: string;
  order_id: string | null;
  assigned_admin_id: string | null;
  created_at: string | null;
  updated_at: string | null;
  customer?: { id: string; name: string; email: string } | null;
  order?: { id: string; order_number: string; store_id?: string | null } | null;
  assigned_admin?: { id: string; name: string } | null;
  messages?: SupportMessageRecord[];
};

export type SupportMessageRecord = {
  id: string;
  sender_type: string;
  sender_id: string | null;
  message: string;
  created_at: string | null;
};

export function canViewSupport(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "support.view");
}

export function canManageSupport(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "support.manage");
}

export function canAssignSupport(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "support.assign");
}

export async function fetchAdminSupportTickets(filters?: {
  status?: string;
  category?: string;
  priority?: string;
}): Promise<SupportTicketRecord[]> {
  const params = new URLSearchParams();
  if (filters?.status) params.set("status", filters.status);
  if (filters?.category) params.set("category", filters.category);
  if (filters?.priority) params.set("priority", filters.priority);
  const qs = params.toString() ? `?${params.toString()}` : "";

  const response = await fetch(`/api/admin/support/tickets${qs}`, {
    credentials: "include",
    cache: "no-store",
  });

  let payload: { success?: boolean; message?: string; data?: SupportTicketRecord[] } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !Array.isArray(payload.data)) {
    throw new AdminSupportApiError(
      payload.message?.trim() || "Unable to load support tickets.",
      response.status,
    );
  }

  return payload.data;
}

export async function fetchAdminSupportTicket(id: string): Promise<SupportTicketRecord> {
  const response = await fetch(`/api/admin/support/tickets/${encodeURIComponent(id)}`, {
    credentials: "include",
    cache: "no-store",
  });

  let payload: { success?: boolean; message?: string; data?: SupportTicketRecord } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminSupportApiError(
      payload.message?.trim() || "Unable to load ticket.",
      response.status,
    );
  }

  return payload.data;
}

export async function assignAdminSupportTicket(
  ticketId: string,
  adminId: string,
): Promise<SupportTicketRecord> {
  const response = await fetch(`/api/admin/support/tickets/${encodeURIComponent(ticketId)}/assign`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ admin_id: adminId }),
  });

  let payload: { success?: boolean; message?: string; data?: SupportTicketRecord } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminSupportApiError(payload.message?.trim() || "Unable to assign ticket.", response.status);
  }

  return payload.data;
}

export async function updateAdminSupportTicketStatus(
  ticketId: string,
  status: string,
): Promise<SupportTicketRecord> {
  const response = await fetch(`/api/admin/support/tickets/${encodeURIComponent(ticketId)}/status`, {
    method: "PATCH",
    credentials: "include",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ status }),
  });

  let payload: { success?: boolean; message?: string; data?: SupportTicketRecord } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminSupportApiError(
      payload.message?.trim() || "Unable to update ticket status.",
      response.status,
    );
  }

  return payload.data;
}

export async function replyAdminSupportTicket(
  ticketId: string,
  message: string,
  waitingForCustomer = false,
): Promise<SupportTicketRecord> {
  const response = await fetch(`/api/admin/support/tickets/${encodeURIComponent(ticketId)}/messages`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ message, waiting_for_customer: waitingForCustomer }),
  });

  let payload: { success?: boolean; message?: string; data?: SupportTicketRecord } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminSupportApiError(payload.message?.trim() || "Unable to send reply.", response.status);
  }

  return payload.data;
}
