import { getCustomerApiToken } from "@/lib/api/customer-auth";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type CheckoutSessionCartItem = {
  id: string;
  product_id: string;
  product_variant_id: string | null;
  quantity: number;
  unit_price: string | number;
  price_snapshot?: string | number | null;
  currency?: string;
  subtotal?: string | number;
  product?: {
    id?: string;
    name?: string;
    slug?: string;
  } | null;
  variant?: {
    id?: string;
    sku?: string | null;
    name?: string | null;
  } | null;
};

export type CheckoutSessionPayload = {
  id: string;
  user_id: string;
  cart_id: string;
  currency: string;
  status: string;
  subtotal: string | number;
  discount_total: string | number;
  discount_breakdown?: {
    applications?: Array<{
      promotion_name?: string;
      promotion_code?: string | null;
      discount_amount?: string;
    }>;
    primary_promotion_code?: string | null;
  } | null;
  promotion_id?: string | null;
  applied_promotion_code?: string | null;
  promotion?: {
    id: string;
    name: string;
    code?: string | null;
    discount_type?: string;
    value?: string | number;
  } | null;
  tax_total: string | number;
  shipping_total: string | number;
  grand_total: string | number;
  shipping_choice?: string | null;
  shipping_method?: string | null;
  agent_name?: string | null;
  agent_contact?: string | null;
  shipping_ready?: boolean;
  is_expired: boolean;
  expires_at?: string | null;
  cart?: {
    id?: string;
    items?: CheckoutSessionCartItem[];
    item_count?: number;
    subtotal?: string | number;
  } | null;
};

export class CheckoutSessionApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly fieldErrors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CheckoutSessionApiError";
  }
}

function getAuthHeaders(token?: string | null): HeadersInit {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CheckoutSessionApiError("Sign in to start checkout.", 401);
  }

  return {
    Accept: "application/json",
    Authorization: `Bearer ${authToken}`,
    "Content-Type": "application/json",
  };
}

function formatApiErrorMessage(
  payload: ApiSuccessResponse<unknown>,
  fallback: string,
): string {
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

async function sessionApiFetch<T>(
  path: string,
  init: RequestInit,
  fallbackError: string,
): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;

  if (!response.ok || payload.success === false) {
    throw new CheckoutSessionApiError(
      formatApiErrorMessage(payload, fallbackError),
      response.status,
      payload.errors,
    );
  }

  return payload.data as T;
}

export function parseSessionMoney(value: string | number | null | undefined): number {
  if (typeof value === "number") {
    return Number.isFinite(value) ? value : 0;
  }
  if (typeof value === "string" && value.trim()) {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }
  return 0;
}

export async function startCheckoutSession(
  token?: string | null,
): Promise<CheckoutSessionPayload> {
  return sessionApiFetch<CheckoutSessionPayload>(
    "/api/checkout/start",
    {
      method: "POST",
      headers: getAuthHeaders(token),
    },
    "Unable to start checkout.",
  );
}

export async function fetchCheckoutSession(
  sessionId: string,
  token?: string | null,
): Promise<CheckoutSessionPayload> {
  return sessionApiFetch<CheckoutSessionPayload>(
    `/api/checkout/${encodeURIComponent(sessionId)}`,
    {
      method: "GET",
      headers: getAuthHeaders(token),
    },
    "Unable to load checkout session.",
  );
}

export async function refreshCheckoutSession(
  sessionId: string,
  token?: string | null,
): Promise<CheckoutSessionPayload> {
  return sessionApiFetch<CheckoutSessionPayload>(
    `/api/checkout/${encodeURIComponent(sessionId)}/refresh`,
    {
      method: "POST",
      headers: getAuthHeaders(token),
    },
    "Unable to refresh checkout session.",
  );
}

export async function cancelCheckoutSession(
  sessionId: string,
  token?: string | null,
): Promise<void> {
  await sessionApiFetch<unknown>(
    `/api/checkout/${encodeURIComponent(sessionId)}`,
    {
      method: "DELETE",
      headers: getAuthHeaders(token),
    },
    "Unable to cancel checkout session.",
  );
}

export async function applyCheckoutShippingChoice(
  sessionId: string,
  input: {
    shipping_choice: string;
    shipping_method?: string | null;
    agent_name?: string | null;
    agent_contact?: string | null;
  },
  token?: string | null,
): Promise<CheckoutSessionPayload> {
  return sessionApiFetch<CheckoutSessionPayload>(
    `/api/checkout/${encodeURIComponent(sessionId)}/shipping-choice`,
    {
      method: "POST",
      headers: getAuthHeaders(token),
      body: JSON.stringify(input),
    },
    "Unable to save shipping choice.",
  );
}

export async function applyPromotionToCheckoutSession(
  sessionId: string,
  code: string,
  token?: string | null,
): Promise<CheckoutSessionPayload> {
  return sessionApiFetch<CheckoutSessionPayload>(
    "/api/promotions/apply",
    {
      method: "POST",
      headers: getAuthHeaders(token),
      body: JSON.stringify({
        code: code.trim(),
        checkout_session_id: sessionId,
      }),
    },
    "Unable to apply promotion code.",
  );
}
