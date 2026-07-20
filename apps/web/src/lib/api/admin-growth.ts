export class AdminGrowthApiError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "AdminGrowthApiError";
  }
}

async function parseJson<T>(res: Response): Promise<T> {
  const payload = (await res.json().catch(() => ({}))) as T & {
    success?: boolean;
    message?: string;
  };
  if (!res.ok) {
    throw new AdminGrowthApiError(payload.message ?? `Growth request failed (${res.status})`);
  }
  return payload;
}

export type GrowthDashboard = {
  active_segments: number;
  total_segment_members: number;
  campaigns_total: number;
  campaigns_completed: number;
  campaigns_running: number;
  journeys_active: number;
  campaign_revenue: number;
  campaign_conversion_rate: number;
  lifecycle_distribution: {
    new: number;
    active: number;
    vip: number;
    inactive: number;
    winback: number;
  };
};

export type GrowthSegment = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  rules: { all?: Array<{ field: string; op: string; value: unknown }> };
  is_active: boolean;
  store_id?: string | null;
  member_count: number;
  last_evaluated_at?: string | null;
};

export type GrowthCampaign = {
  id: string;
  name: string;
  description?: string | null;
  campaign_type: string;
  status: string;
  growth_segment_id?: string | null;
  segment?: { id: string; code: string; name: string; member_count?: number } | null;
  channel: string;
  channels?: string[] | null;
  message_title?: string | null;
  message_body: string;
  promotion_code?: string | null;
  bonus_points?: number | null;
  sent_count: number;
  delivered_count: number;
  opened_count: number;
  clicked_count: number;
  redeemed_count: number;
  purchased_count: number;
  revenue_generated?: string | number;
};

export type GrowthJourney = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  trigger_type: string;
  trigger_config?: Record<string, unknown> | null;
  is_active: boolean;
  segment?: { id: string; code: string; name: string } | null;
  campaign?: { id: string; name: string } | null;
};

export type CampaignAnalytics = {
  sent: number;
  delivered: number;
  opened: number;
  clicked: number;
  redeemed: number;
  purchased: number;
  conversion_rate: number;
  revenue_generated: number;
};

function unwrapList<T>(payload: { data?: T[] | { data?: T[] } }): T[] {
  const raw = payload.data;
  if (Array.isArray(raw)) return raw;
  if (raw && typeof raw === "object" && Array.isArray((raw as { data?: T[] }).data)) {
    return (raw as { data: T[] }).data;
  }
  return [];
}

export async function fetchGrowthDashboard() {
  const res = await fetch("/api/admin/growth/dashboard", { cache: "no-store" });
  const payload = await parseJson<{ data: GrowthDashboard }>(res);
  return payload.data;
}

export async function fetchGrowthSegments() {
  const res = await fetch("/api/admin/growth/segments", { cache: "no-store" });
  const payload = await parseJson<{ data: GrowthSegment[] | { data: GrowthSegment[] } }>(res);
  return unwrapList(payload);
}

export async function createGrowthSegment(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/growth/segments", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: GrowthSegment }>(res);
}

export async function refreshGrowthSegment(id: string) {
  const res = await fetch(`/api/admin/growth/segments/${id}/refresh`, { method: "POST" });
  return parseJson<{ data: GrowthSegment; message?: string }>(res);
}

export async function fetchGrowthCampaigns() {
  const res = await fetch("/api/admin/growth/campaigns", { cache: "no-store" });
  const payload = await parseJson<{ data: GrowthCampaign[] | { data: GrowthCampaign[] } }>(res);
  return unwrapList(payload);
}

export async function createGrowthCampaign(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/growth/campaigns", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: GrowthCampaign }>(res);
}

export async function sendGrowthCampaign(id: string) {
  const res = await fetch(`/api/admin/growth/campaigns/${id}/send`, { method: "POST" });
  return parseJson<{ data: GrowthCampaign; message?: string }>(res);
}

export async function fetchCampaignAnalytics(id: string) {
  const res = await fetch(`/api/admin/growth/campaigns/${id}/analytics`, { cache: "no-store" });
  const payload = await parseJson<{ data: { analytics: CampaignAnalytics } }>(res);
  return payload.data.analytics;
}

export async function fetchGrowthJourneys() {
  const res = await fetch("/api/admin/growth/journeys", { cache: "no-store" });
  const payload = await parseJson<{ data: GrowthJourney[] | { data: GrowthJourney[] } }>(res);
  return unwrapList(payload);
}

export async function createGrowthJourney(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/growth/journeys", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: GrowthJourney }>(res);
}

export async function runGrowthJourneys(sendCampaigns = false) {
  const res = await fetch("/api/admin/growth/journeys/run", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ send_campaigns: sendCampaigns }),
  });
  return parseJson<{ data: { enrolled: number; campaigns_sent: number } }>(res);
}
