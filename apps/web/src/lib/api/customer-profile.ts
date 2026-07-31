import { getCustomerApiToken } from "@/lib/api/customer-auth";

export type CustomerProfile = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
};

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
};

export class CustomerProfileApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerProfileApiError";
  }
}

export async function fetchCustomerProfile(): Promise<CustomerProfile | null> {
  const authToken = getCustomerApiToken();
  if (!authToken) {
    return null;
  }

  const response = await fetch("/api/profile", {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
    },
    cache: "no-store",
  });

  const payload = (await response.json()) as ApiSuccessResponse<CustomerProfile>;
  if (!response.ok || payload.success === false || !payload.data) {
    throw new CustomerProfileApiError(
      payload.message?.trim() || "Unable to load your profile.",
      response.status,
    );
  }

  return payload.data;
}
