import { getCustomerApiToken } from "@/lib/api/customer-auth";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type DeliveryOptionPayload = {
  id: string;
  order_id: string;
  delivery_type: string;
  delivery_type_label?: string | null;
  shipping_method?: string | null;
  shipping_method_label?: string | null;
  delivery_status: string;
  delivery_status_label?: string | null;
  agent_name?: string | null;
  agent_contact?: string | null;
  notes?: string | null;
  confirmed_at?: string | null;
};

export type DeliveryAvailableOptions = {
  market: string;
  market_label: string;
  delivery_types: Array<{ value: string; label: string }>;
  shipping_methods: Array<{ value: string; label: string }>;
};

export type DeliveryOptionShowPayload = {
  delivery_option: DeliveryOptionPayload | null;
  available: DeliveryAvailableOptions;
};

export class DeliveryOptionApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "DeliveryOptionApiError";
  }
}

function authHeaders(token?: string | null): HeadersInit {
  const authToken = token ?? getCustomerApiToken();
  if (!authToken) {
    throw new DeliveryOptionApiError("Sign in to manage delivery options.", 401);
  }
  return {
    Accept: "application/json",
    Authorization: `Bearer ${authToken}`,
    "Content-Type": "application/json",
  };
}

function formatError(payload: ApiSuccessResponse<unknown>, fallback: string): string {
  if (payload.message?.trim()) return payload.message.trim();
  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) return first.trim();
  }
  return fallback;
}

async function apiFetch<T>(
  path: string,
  init: RequestInit,
  fallback: string,
): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new DeliveryOptionApiError(formatError(payload, fallback), response.status);
  }
  return payload.data as T;
}

export async function fetchDeliveryOption(
  orderNumber: string,
  token?: string | null,
): Promise<DeliveryOptionShowPayload> {
  return apiFetch<DeliveryOptionShowPayload>(
    `/api/orders/${encodeURIComponent(orderNumber)}/delivery-option`,
    { method: "GET", headers: authHeaders(token) },
    "Unable to load delivery options.",
  );
}

export async function selectDeliveryOption(
  orderNumber: string,
  body: {
    delivery_type: string;
    shipping_method?: string | null;
    agent_name?: string | null;
    agent_contact?: string | null;
    notes?: string | null;
  },
  token?: string | null,
): Promise<DeliveryOptionPayload> {
  return apiFetch<DeliveryOptionPayload>(
    `/api/orders/${encodeURIComponent(orderNumber)}/delivery-option`,
    {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify(body),
    },
    "Unable to select delivery option.",
  );
}

export async function updateDeliveryOption(
  orderNumber: string,
  body: {
    delivery_type?: string;
    shipping_method?: string | null;
    agent_name?: string | null;
    agent_contact?: string | null;
    notes?: string | null;
    delivery_status?: string;
  },
  token?: string | null,
): Promise<DeliveryOptionPayload> {
  return apiFetch<DeliveryOptionPayload>(
    `/api/orders/${encodeURIComponent(orderNumber)}/delivery-option`,
    {
      method: "PATCH",
      headers: authHeaders(token),
      body: JSON.stringify(body),
    },
    "Unable to update delivery option.",
  );
}
