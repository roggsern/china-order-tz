import { getCustomerApiToken } from "@/lib/api/customer-auth";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export class CustomerSupportApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "CustomerSupportApiError";
  }
}

export type CustomerSupportTicket = {
  id: string;
  ticket_number: string;
  subject: string;
  category: string;
  category_label: string;
  priority: string;
  status: string;
  status_label: string;
  order_id: string | null;
  created_at: string | null;
  updated_at: string | null;
  order?: { id: string; order_number: string } | null;
  messages?: CustomerSupportMessage[];
};

export type CustomerSupportMessage = {
  id: string;
  sender_type: string;
  message: string;
  created_at: string | null;
};

export type CreateCustomerSupportTicketInput = {
  subject: string;
  category: string;
  message: string;
  order_id?: string | null;
  priority?: string;
};

export const CUSTOMER_SUPPORT_CATEGORIES = [
  { value: "order_issue", label: "Order issue" },
  { value: "payment_issue", label: "Payment issue" },
  { value: "delivery_issue", label: "Delivery issue" },
  { value: "product_issue", label: "Product issue" },
  { value: "return_issue", label: "Return issue" },
  { value: "general", label: "General" },
] as const;

export function formatSupportApiError(payload: ApiSuccessResponse<unknown>, fallback: string): string {
  if (payload.message?.trim()) {
    return payload.message.trim();
  }

  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) {
      return first.trim();
    }
  }

  return fallback;
}

export function mapSupportTicketsFromResponse(
  payload: ApiSuccessResponse<CustomerSupportTicket[]>,
): CustomerSupportTicket[] {
  if (!Array.isArray(payload.data)) {
    return [];
  }

  return payload.data;
}

export function mapSupportTicketFromResponse(
  payload: ApiSuccessResponse<CustomerSupportTicket>,
): CustomerSupportTicket | null {
  return payload.data ?? null;
}

export function normalizeCreateSupportTicketInput(
  input: CreateCustomerSupportTicketInput,
): CreateCustomerSupportTicketInput {
  return {
    subject: input.subject.trim(),
    category: input.category.trim(),
    message: input.message.trim(),
    order_id: input.order_id ?? null,
    priority: input.priority?.trim() || undefined,
  };
}

export function isSupportTicketClosed(status: string): boolean {
  return status === "closed" || status === "resolved";
}

async function customerSupportFetch<T>(
  path: string,
  init: RequestInit,
  fallback: string,
): Promise<T> {
  const authToken = getCustomerApiToken();
  if (!authToken) {
    throw new CustomerSupportApiError("Sign in to contact support.", 401);
  }

  const response = await fetch(path, {
    ...init,
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
      ...(init.body ? { "Content-Type": "application/json" } : {}),
      ...(init.headers ?? {}),
    },
    cache: "no-store",
  });

  let payload: ApiSuccessResponse<T> = {};
  try {
    payload = (await response.json()) as ApiSuccessResponse<T>;
  } catch {
    payload = {};
  }

  if (!response.ok || payload.success === false) {
    throw new CustomerSupportApiError(formatSupportApiError(payload, fallback), response.status);
  }

  return payload.data as T;
}

export async function fetchCustomerSupportTickets(): Promise<CustomerSupportTicket[]> {
  const data = await customerSupportFetch<CustomerSupportTicket[]>(
    "/api/account/support/tickets",
    { method: "GET" },
    "Unable to load support tickets.",
  );

  return Array.isArray(data) ? data : [];
}

export async function fetchCustomerSupportTicket(id: string): Promise<CustomerSupportTicket> {
  const ticket = await customerSupportFetch<CustomerSupportTicket>(
    `/api/account/support/tickets/${encodeURIComponent(id)}`,
    { method: "GET" },
    "Unable to load support ticket.",
  );

  if (!ticket) {
    throw new CustomerSupportApiError("Unable to load support ticket.", 404);
  }

  return ticket;
}

export async function createCustomerSupportTicket(
  body: CreateCustomerSupportTicketInput,
): Promise<CustomerSupportTicket> {
  const payload = normalizeCreateSupportTicketInput(body);
  const ticket = await customerSupportFetch<CustomerSupportTicket>(
    "/api/account/support/tickets",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
    "Unable to create support ticket.",
  );

  if (!ticket) {
    throw new CustomerSupportApiError("Unable to create support ticket.", 422);
  }

  return ticket;
}

export async function replyCustomerSupportTicket(
  ticketId: string,
  message: string,
): Promise<CustomerSupportTicket> {
  const ticket = await customerSupportFetch<CustomerSupportTicket>(
    `/api/account/support/tickets/${encodeURIComponent(ticketId)}/messages`,
    {
      method: "POST",
      body: JSON.stringify({ message: message.trim() }),
    },
    "Unable to send reply.",
  );

  if (!ticket) {
    throw new CustomerSupportApiError("Unable to send reply.", 422);
  }

  return ticket;
}
