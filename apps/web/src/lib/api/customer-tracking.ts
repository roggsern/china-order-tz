import { getCustomerApiToken } from "@/lib/api/customer-auth";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
};

export type CustomerTrackingPayload = {
  order_number: string;
  current_status: string;
  current_status_label?: string | null;
  source?: string | null;
  shipment_summary?: {
    id: string;
    shipment_number: string;
    transport_mode?: string | null;
    status?: string | null;
    carrier_name?: string | null;
  } | null;
  timeline: Array<{
    id?: string | null;
    event_type?: string;
    event_type_label?: string | null;
    step?: string;
    description?: string | null;
    location?: string | null;
    event_at?: string | null;
    completed?: boolean;
    completed_at?: string | null;
  }>;
};

export class CustomerTrackingApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerTrackingApiError";
  }
}

export async function fetchCustomerOrderTracking(
  orderNumber: string,
  token?: string | null,
): Promise<CustomerTrackingPayload> {
  const authToken = token ?? getCustomerApiToken();
  if (!authToken) {
    throw new CustomerTrackingApiError("Sign in to view tracking.", 401);
  }

  const response = await fetch(
    `/api/orders/${encodeURIComponent(orderNumber)}/tracking`,
    {
      method: "GET",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${authToken}`,
      },
      cache: "no-store",
    },
  );

  const payload = (await response.json()) as ApiSuccessResponse<CustomerTrackingPayload>;
  if (!response.ok || payload.success === false || !payload.data) {
    throw new CustomerTrackingApiError(
      payload.message?.trim() || "Unable to load tracking.",
      response.status,
    );
  }
  return payload.data;
}
