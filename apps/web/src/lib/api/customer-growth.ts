import { getCustomerApiToken } from "@/lib/api/customer-auth";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
};

export class CustomerGrowthApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerGrowthApiError";
  }
}

export type CustomerGrowthOffer = {
  campaign_id: string;
  name?: string | null;
  title?: string | null;
  body?: string | null;
  promotion_code?: string | null;
  bonus_points?: number | null;
  channel?: string;
  status?: string;
};

export type CustomerGrowthHistoryItem = {
  campaign_id: string;
  name?: string | null;
  status?: string;
  channel?: string;
  sent_at?: string | null;
};

export type CustomerGrowthOffersPayload = {
  offers: CustomerGrowthOffer[];
  history: CustomerGrowthHistoryItem[];
  growth_stage?: string | null;
  benefits?: {
    marketing_opt_in?: boolean;
    loyalty_points?: number;
    loyalty_tier?: string | null;
  };
};

async function customerGet<T>(path: string, fallback: string): Promise<T> {
  const token = getCustomerApiToken();
  if (!token) throw new CustomerGrowthApiError("Sign in to view offers.", 401);

  const response = await fetch(path, {
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    cache: "no-store",
  });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new CustomerGrowthApiError(payload.message ?? fallback, response.status);
  }
  return payload.data as T;
}

export function fetchCustomerGrowthOffers() {
  return customerGet<CustomerGrowthOffersPayload>("/api/growth/offers", "Unable to load offers.");
}

export function fetchCustomerGrowthHistory() {
  return customerGet<CustomerGrowthHistoryItem[]>("/api/growth/history", "Unable to load history.");
}
