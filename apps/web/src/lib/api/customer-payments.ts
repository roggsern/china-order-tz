import { getCustomerApiToken } from "@/lib/api/customer-auth";

export type BackendPaymentPreparation = {
  id: string;
  reference: string | null;
  order_id: string;
  order_number?: string;
  amount: string | number;
  currency: string;
  payment_method: string;
  status: string;
  ready_for_payment: boolean;
};

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export class CustomerPaymentApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerPaymentApiError";
  }
}

/** Laravel payment_method values accepted by POST /orders/{order}/payments. */
export type BackendPaymentMethod = "mpesa" | "nmb" | "card" | "cash" | "bank_transfer";

/**
 * Maps storefront payment codes to Laravel PaymentMethod enum values.
 * Returns null when the method is frontend-only (e.g. Selcom).
 */
export function toBackendPaymentMethod(
  method: string,
): BackendPaymentMethod | null {
  switch (method) {
    case "mpesa":
      return "mpesa";
    case "nmb":
      return "nmb";
    case "bank_transfer":
      return "bank_transfer";
    case "cod":
      return "cash";
    case "card":
      return "card";
    default:
      return null;
  }
}

/**
 * Creates or reuses a Laravel payment record for an order.
 * POST /api/v1/orders/{order}/payments via BFF.
 */
export async function prepareOrderPayment(
  orderId: string,
  paymentMethod: BackendPaymentMethod,
  token?: string | null,
): Promise<BackendPaymentPreparation> {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new CustomerPaymentApiError("Please sign in to continue with payment.", 401);
  }

  const response = await fetch(`/api/orders/${encodeURIComponent(orderId)}/payments`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ payment_method: paymentMethod }),
    cache: "no-store",
  });

  const raw = await response.text();
  let payload: ApiSuccessResponse<BackendPaymentPreparation>;

  try {
    payload = JSON.parse(raw) as ApiSuccessResponse<BackendPaymentPreparation>;
  } catch {
    throw new CustomerPaymentApiError(
      response.status === 404
        ? "Payment API route was not found. Please refresh and try again."
        : "Unexpected payment response from the server.",
      response.status,
    );
  }

  if (!response.ok || payload.success === false || !payload.data?.id) {
    const firstError = payload.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;

    throw new CustomerPaymentApiError(
      firstError?.trim() ||
        payload.message?.trim() ||
        "Unable to prepare payment for this order.",
      response.status,
    );
  }

  return payload.data;
}
