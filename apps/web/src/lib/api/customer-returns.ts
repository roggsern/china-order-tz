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

export type CustomerReturnItem = {
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

export type CustomerRefundTransaction = {
  id: string;
  amount: number | string;
  currency?: string;
  status: string;
  status_label?: string | null;
  method?: string | null;
  reference?: string | null;
  created_at?: string | null;
};

export type CustomerReturnRequest = {
  id: string;
  order_id: string;
  customer_id: string;
  status: string;
  status_label?: string | null;
  reason: string;
  description?: string | null;
  customer_notes?: string | null;
  admin_notes?: string | null;
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
  items?: CustomerReturnItem[];
  refund_transactions?: CustomerRefundTransaction[];
  latest_refund?: CustomerRefundTransaction | null;
};

export type CreateCustomerReturnPayload = {
  reason: string;
  description?: string | null;
  customer_notes?: string | null;
  items: Array<{
    order_item_id: string;
    quantity: number;
    reason?: string | null;
    replacement_requested?: boolean;
  }>;
};

export class CustomerReturnsApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerReturnsApiError";
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
  token?: string | null,
): Promise<T> {
  const authToken = token ?? getCustomerApiToken();
  if (!authToken) {
    throw new CustomerReturnsApiError("Sign in to manage returns.", 401);
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
    throw new CustomerReturnsApiError(formatError(payload, fallback), response.status);
  }

  return payload.data as T;
}

function unwrapList(data: unknown): CustomerReturnRequest[] {
  if (Array.isArray(data)) return data as CustomerReturnRequest[];
  if (data && typeof data === "object" && Array.isArray((data as { data: unknown }).data)) {
    return (data as { data: CustomerReturnRequest[] }).data;
  }
  return [];
}

export async function createCustomerReturn(
  orderNumber: string,
  body: CreateCustomerReturnPayload,
  token?: string | null,
): Promise<CustomerReturnRequest> {
  return customerFetch<CustomerReturnRequest>(
    `/api/orders/${encodeURIComponent(orderNumber)}/returns`,
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    "Unable to submit return request.",
    token,
  );
}

export async function fetchCustomerReturns(
  token?: string | null,
): Promise<CustomerReturnRequest[]> {
  const data = await customerFetch<
    CustomerReturnRequest[] | { data: CustomerReturnRequest[] }
  >(
    "/api/returns",
    { method: "GET" },
    "Unable to load returns.",
    token,
  );
  return unwrapList(data);
}

export async function fetchCustomerReturn(
  returnId: string,
  token?: string | null,
): Promise<CustomerReturnRequest> {
  return customerFetch<CustomerReturnRequest>(
    `/api/returns/${encodeURIComponent(returnId)}`,
    { method: "GET" },
    "Unable to load return request.",
    token,
  );
}
