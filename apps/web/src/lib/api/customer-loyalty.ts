import { getCustomerApiToken } from "@/lib/api/customer-auth";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  meta?: Record<string, unknown>;
};

export class CustomerLoyaltyApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerLoyaltyApiError";
  }
}

export type CustomerLoyaltyProfile = {
  id: string;
  loyalty_number: string;
  status: string;
  points_balance: number;
  lifetime_points: number;
  lifetime_redeemed: number;
  tier?: { id: string; code: string; name: string; earn_multiplier?: string | number } | null;
  enrolled_at?: string;
};

export type CustomerLoyaltyTransaction = {
  id: string;
  entry_type: string;
  points: number;
  balance_after: number;
  reason?: string | null;
  created_at?: string;
};

export type CustomerLoyaltyReward = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  points_cost: number;
  reward_type: string;
};

async function customerGet<T>(path: string, fallback: string): Promise<T> {
  const token = getCustomerApiToken();
  if (!token) throw new CustomerLoyaltyApiError("Sign in to view loyalty.", 401);

  const response = await fetch(path, {
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    cache: "no-store",
  });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new CustomerLoyaltyApiError(payload.message ?? fallback, response.status);
  }
  return payload.data as T;
}

export async function fetchCustomerLoyaltyProfile() {
  return customerGet<CustomerLoyaltyProfile>("/api/loyalty/profile", "Unable to load loyalty profile.");
}

export async function fetchCustomerLoyaltyTransactions() {
  const token = getCustomerApiToken();
  if (!token) throw new CustomerLoyaltyApiError("Sign in to view loyalty.", 401);
  const response = await fetch("/api/loyalty/transactions", {
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    cache: "no-store",
  });
  const payload = (await response.json()) as ApiSuccessResponse<CustomerLoyaltyTransaction[]>;
  if (!response.ok || payload.success === false) {
    throw new CustomerLoyaltyApiError(payload.message ?? "Unable to load transactions.", response.status);
  }
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchCustomerLoyaltyRewards() {
  return customerGet<CustomerLoyaltyReward[]>("/api/loyalty/rewards", "Unable to load rewards.");
}

export async function redeemCustomerLoyaltyReward(rewardId: string) {
  const token = getCustomerApiToken();
  if (!token) throw new CustomerLoyaltyApiError("Sign in to redeem.", 401);
  const response = await fetch("/api/loyalty/redeem", {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({ reward_id: rewardId }),
  });
  const payload = (await response.json()) as ApiSuccessResponse<{
    promotion_code: string | null;
    account: CustomerLoyaltyProfile;
  }>;
  if (!response.ok || payload.success === false) {
    throw new CustomerLoyaltyApiError(payload.message ?? "Unable to redeem reward.", response.status);
  }
  return payload.data!;
}
