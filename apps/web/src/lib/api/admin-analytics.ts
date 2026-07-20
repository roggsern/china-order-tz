export type AnalyticsFilters = {
  from?: string;
  to?: string;
  store_id?: string;
  cashier_id?: string;
  payment_method?: string;
  pos_only?: boolean;
};

export type ChartPoint = { x: string | number; y: number; label?: string };
export type ChartPayload = {
  type: string;
  key: string;
  label: string;
  series: Array<{ name: string; points: ChartPoint[] }>;
};

export type AnalyticsDashboard = {
  period: { from: string; to: string };
  kpis: Record<string, number>;
  charts: ChartPayload[];
  drill_down?: Record<string, string>;
};

export const ANALYTICS_SECTIONS = [
  "dashboard",
  "sales",
  "profit",
  "inventory",
  "returns",
  "customers",
  "promotions",
  "loyalty",
  "growth",
  "stores",
  "sessions",
] as const;

export type AnalyticsSection = (typeof ANALYTICS_SECTIONS)[number];

export const ANALYTICS_EXPORT_TYPES = [
  "sales",
  "profit",
  "payments",
  "inventory",
  "returns",
  "customers",
  "stores",
  "sessions",
  "promotions",
  "loyalty",
  "growth",
] as const;

export type AnalyticsExportType = (typeof ANALYTICS_EXPORT_TYPES)[number];

function toQuery(filters: AnalyticsFilters): string {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    params.set(key, String(value));
  });
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

async function parseJson<T>(res: Response): Promise<T> {
  const payload = (await res.json().catch(() => null)) as T & {
    success?: boolean;
    message?: string;
    data?: unknown;
  };
  if (!res.ok) {
    throw new Error(
      (payload as { message?: string })?.message || `Analytics request failed (${res.status})`,
    );
  }
  return payload;
}

export async function fetchAnalyticsSection<T = Record<string, unknown>>(
  section: AnalyticsSection | "payments",
  filters: AnalyticsFilters = {},
): Promise<T> {
  const res = await fetch(`/api/admin/analytics/${section}${toQuery(filters)}`, {
    cache: "no-store",
  });
  const payload = await parseJson<{ data: T }>(res);
  return payload.data;
}

export async function downloadAnalyticsExport(
  type: AnalyticsExportType,
  format: "csv" | "xlsx",
  filters: AnalyticsFilters = {},
): Promise<void> {
  const params = new URLSearchParams(toQuery(filters).replace(/^\?/, ""));
  params.set("format", format);
  const res = await fetch(`/api/admin/analytics/${type}/export?${params.toString()}`, {
    cache: "no-store",
  });
  if (!res.ok) {
    const err = await res.json().catch(() => null);
    throw new Error(err?.message || `Export failed (${res.status})`);
  }
  const blob = await res.blob();
  const disposition = res.headers.get("Content-Disposition") || "";
  const match = /filename="?([^"]+)"?/i.exec(disposition);
  const filename = match?.[1] || `analytics-${type}.${format}`;
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
