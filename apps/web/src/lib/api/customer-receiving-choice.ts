import { getCustomerApiToken } from "@/lib/api/customer-auth";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type ReceivingChoiceSnapshot = {
  eligible: boolean;
  can_select: boolean;
  selected_method: "self_pickup" | "negotiated_delivery" | null;
  selected_method_label?: string | null;
  selected_at?: string | null;
};

export type ReceivingMethodSelectionResponse = {
  delivery_option: {
    last_mile_receiving_method?: string | null;
    last_mile_receiving_method_label?: string | null;
    last_mile_selected_at?: string | null;
  };
  receiving_choice: ReceivingChoiceSnapshot;
};

export class ReceivingChoiceApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "ReceivingChoiceApiError";
  }
}

function authHeaders(token?: string | null): HeadersInit {
  const authToken = token ?? getCustomerApiToken();
  if (!authToken) {
    throw new ReceivingChoiceApiError("Sign in to choose how to receive your order.", 401);
  }

  return {
    Accept: "application/json",
    Authorization: `Bearer ${authToken}`,
    "Content-Type": "application/json",
  };
}

function formatError(payload: ApiSuccessResponse<unknown>, fallback: string): string {
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

export async function selectReceivingMethod(
  orderNumber: string,
  receivingMethod: "self_pickup" | "negotiated_delivery",
  token?: string | null,
): Promise<ReceivingMethodSelectionResponse> {
  const response = await fetch(
    `/api/orders/${encodeURIComponent(orderNumber.trim())}/receiving-method`,
    {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({ receiving_method: receivingMethod }),
      cache: "no-store",
    },
  );

  const payload = (await response.json()) as ApiSuccessResponse<ReceivingMethodSelectionResponse>;

  if (!response.ok || payload.success === false || !payload.data) {
    throw new ReceivingChoiceApiError(
      formatError(payload, "Unable to save your receiving choice."),
      response.status,
    );
  }

  return payload.data;
}

export function parseReceivingChoiceSnapshot(value: unknown): ReceivingChoiceSnapshot | null {
  if (typeof value !== "object" || value === null) {
    return null;
  }

  const snapshot = value as Record<string, unknown>;

  return {
    eligible: Boolean(snapshot.eligible),
    can_select: Boolean(snapshot.can_select),
    selected_method:
      snapshot.selected_method === "self_pickup" ||
      snapshot.selected_method === "negotiated_delivery"
        ? snapshot.selected_method
        : null,
    selected_method_label:
      typeof snapshot.selected_method_label === "string"
        ? snapshot.selected_method_label
        : null,
    selected_at: typeof snapshot.selected_at === "string" ? snapshot.selected_at : null,
  };
}
