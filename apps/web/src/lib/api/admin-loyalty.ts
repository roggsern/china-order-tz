export class AdminLoyaltyApiError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "AdminLoyaltyApiError";
  }
}

async function parseJson<T>(res: Response): Promise<T> {
  const payload = (await res.json().catch(() => ({}))) as T & {
    success?: boolean;
    message?: string;
  };
  if (!res.ok) {
    throw new AdminLoyaltyApiError(payload.message ?? `Loyalty request failed (${res.status})`);
  }
  return payload;
}

export type LoyaltyDashboard = {
  active_customers: number;
  points_issued: number;
  points_redeemed: number;
  reward_redemptions: number;
  tier_distribution: Array<{ tier_id?: string; code?: string; name: string; customers: number }>;
  top_customers: Array<{
    loyalty_number: string;
    customer?: string;
    points_balance: number;
    lifetime_points: number;
    tier?: string;
  }>;
};

export type LoyaltyAccount = {
  id: string;
  loyalty_number: string;
  status: string;
  points_balance: number;
  lifetime_points: number;
  lifetime_redeemed: number;
  tier?: { id: string; code: string; name: string; earn_multiplier?: string | number } | null;
  customer?: { id?: string; name?: string; email?: string; customer_code?: string } | null;
  enrolled_at?: string;
};

export type LoyaltyTier = {
  id: string;
  code: string;
  name: string;
  sort_order: number;
  min_lifetime_points: number;
  min_lifetime_spend: string | number;
  min_orders: number;
  earn_multiplier: string | number;
  is_active: boolean;
};

export type LoyaltyRule = {
  id: string;
  code: string;
  name: string;
  rule_type: string;
  is_active: boolean;
  priority: number;
  spend_amount?: string | number | null;
  points_awarded: number;
  expiry_months?: number | null;
};

export type LoyaltyReward = {
  id: string;
  code: string;
  name: string;
  reward_type: string;
  is_active: boolean;
  points_cost: number;
  discount_type?: string | null;
  discount_value?: string | number | null;
};

export async function fetchLoyaltyDashboard() {
  const res = await fetch("/api/admin/loyalty/dashboard", { cache: "no-store" });
  const payload = await parseJson<{ data: LoyaltyDashboard }>(res);
  return payload.data;
}

export async function fetchLoyaltyCustomers(search = "") {
  const params = new URLSearchParams();
  if (search.trim()) params.set("search", search.trim());
  const res = await fetch(`/api/admin/loyalty/customers?${params}`, { cache: "no-store" });
  const payload = await parseJson<{ data: LoyaltyAccount[] }>(res);
  return payload.data ?? [];
}

export async function fetchLoyaltyTiers() {
  const res = await fetch("/api/admin/loyalty/tiers", { cache: "no-store" });
  const payload = await parseJson<{ data: LoyaltyTier[] }>(res);
  return payload.data ?? [];
}

export async function createLoyaltyTier(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/loyalty/tiers", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: LoyaltyTier }>(res);
}

export async function fetchLoyaltyRules() {
  const res = await fetch("/api/admin/loyalty/rules", { cache: "no-store" });
  const payload = await parseJson<{ data: LoyaltyRule[] }>(res);
  return payload.data ?? [];
}

export async function createLoyaltyRule(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/loyalty/rules", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: LoyaltyRule }>(res);
}

export async function updateLoyaltyRule(id: string, body: Record<string, unknown>) {
  const res = await fetch(`/api/admin/loyalty/rules/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: LoyaltyRule }>(res);
}

export async function fetchLoyaltyRewards() {
  const res = await fetch("/api/admin/loyalty/rewards", { cache: "no-store" });
  const payload = await parseJson<{ data: LoyaltyReward[] }>(res);
  return payload.data ?? [];
}

export async function createLoyaltyReward(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/loyalty/rewards", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: LoyaltyReward }>(res);
}

export async function updateLoyaltyReward(id: string, body: Record<string, unknown>) {
  const res = await fetch(`/api/admin/loyalty/rewards/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: LoyaltyReward }>(res);
}

export async function adjustLoyaltyPoints(accountId: string, points: number, reason: string) {
  const res = await fetch(`/api/admin/loyalty/customers/${accountId}/adjust`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ points, reason }),
  });
  return parseJson<{ data: { account: LoyaltyAccount } }>(res);
}

export async function lookupPosLoyalty(params: { customer_id?: string; loyalty_number?: string; search?: string }) {
  const qs = new URLSearchParams();
  if (params.customer_id) qs.set("customer_id", params.customer_id);
  if (params.loyalty_number) qs.set("loyalty_number", params.loyalty_number);
  if (params.search) qs.set("search", params.search);
  const res = await fetch(`/api/admin/pos/loyalty/lookup?${qs}`, { cache: "no-store" });
  const payload = await parseJson<{ data: LoyaltyAccount | null }>(res);
  return payload.data;
}

export async function redeemPosLoyalty(accountId: string, rewardId: string) {
  const res = await fetch(`/api/admin/pos/loyalty/${accountId}/redeem`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ reward_id: rewardId, channel: "pos" }),
  });
  return parseJson<{
    data: { promotion_code: string | null; account: LoyaltyAccount };
    message?: string;
  }>(res);
}
