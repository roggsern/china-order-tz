import { getCustomerApiToken } from "@/lib/api/customer-auth";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type PaymentTransactionPayload = {
  id: string;
  order_id: string;
  provider: string;
  provider_reference?: string | null;
  external_transaction_id?: string | null;
  merchant_reference: string;
  currency: string;
  amount: string | number;
  status: string;
  checkout_url?: string | null;
  success_indicator?: string | null;
  request_payload?: Record<string, unknown> | null;
  response_payload?: Record<string, unknown> | null;
  initiated_at?: string | null;
  callback_received_at?: string | null;
  completed_at?: string | null;
  order?: {
    id: string;
    order_number: string;
    status?: string;
    grand_total?: string | number;
    currency?: string;
  } | null;
};

export class PaymentOrchestratorApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "PaymentOrchestratorApiError";
  }
}

function getAuthHeaders(token?: string | null): HeadersInit {
  const authToken = token ?? getCustomerApiToken();
  if (!authToken) {
    throw new PaymentOrchestratorApiError("Sign in to manage payments.", 401);
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
    throw new PaymentOrchestratorApiError(
      formatError(payload, fallback),
      response.status,
    );
  }
  return payload.data as T;
}

export function parsePaymentAmount(value: string | number | null | undefined): number {
  if (typeof value === "number") return Number.isFinite(value) ? value : 0;
  if (typeof value === "string" && value.trim()) {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }
  return 0;
}

export async function startPaymentTransaction(
  orderId: string,
  provider?: string,
  token?: string | null,
): Promise<PaymentTransactionPayload> {
  return apiFetch<PaymentTransactionPayload>(
    `/api/payments/start/${encodeURIComponent(orderId)}`,
    {
      method: "POST",
      headers: getAuthHeaders(token),
      body: JSON.stringify(provider ? { provider } : {}),
    },
    "Unable to start payment.",
  );
}

export async function fetchPaymentTransaction(
  transactionId: string,
  token?: string | null,
): Promise<PaymentTransactionPayload> {
  return apiFetch<PaymentTransactionPayload>(
    `/api/payments/${encodeURIComponent(transactionId)}`,
    {
      method: "GET",
      headers: getAuthHeaders(token),
    },
    "Unable to load payment transaction.",
  );
}

export async function refreshPaymentTransaction(
  transactionId: string,
  token?: string | null,
): Promise<PaymentTransactionPayload> {
  return apiFetch<PaymentTransactionPayload>(
    `/api/payments/${encodeURIComponent(transactionId)}/refresh`,
    {
      method: "POST",
      headers: getAuthHeaders(token),
    },
    "Unable to refresh payment transaction.",
  );
}
