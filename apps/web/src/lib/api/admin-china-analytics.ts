import { hasAdminPermission } from "@/lib/api/admin-me";

type ApiResponse<T> = { success?: boolean; data?: T; message?: string };

export class AdminChinaAnalyticsApiError extends Error {
  constructor(message: string, public readonly statusCode?: number) {
    super(message);
    this.name = "AdminChinaAnalyticsApiError";
  }
}

export type ChinaAnalyticsOverview = {
  currency: string;
  total_china_products: number;
  total_imported_quantity: number;
  total_import_value: string;
  total_landed_cost: string;
  total_sales_generated: string;
  gross_profit: string;
  gross_margin_percentage: string;
  units_sold: number;
  orders_count: number;
  volume_trend: { period: string; units: number; revenue: string; landed_cost: string }[];
  revenue_vs_cost: { revenue: string; cost: string; profit: string };
};

export type ChinaLandedCostAnalytics = {
  average_landed_cost_per_unit: string;
  components: {
    supplier_cost: string;
    china_logistics_and_freight: string;
    warehouse_china_costs: string;
    other_import_costs: string;
    total_landed_cost: string;
  };
  by_product: { product_name: string; average_landed_cost: string; units: number }[];
  by_category: { category_name: string; average_landed_cost: string; units: number }[];
  by_supplier: { supplier_name: string; average_landed_cost: string; units: number }[];
};

export type ChinaSupplierRankingRow = {
  rank: number;
  supplier_name: string;
  products_supplied: number;
  quantity_received: number;
  quantity_sold: number;
  revenue: string;
  gross_profit: string;
  margin_percentage: string;
};

export type ChinaCategoryRow = {
  category_name: string;
  imported_units: number;
  revenue: string;
  gross_profit: string;
  margin_percentage: string;
};

export type ChinaShipmentEconomics = {
  shipments_count: number;
  average_shipment_cost: string;
  total_freight_cost: string;
  average_transit_days: number | null;
  cost_per_unit: string;
  margin_by_supplier: { label: string; margin_percentage: string }[];
  margin_by_category: { label: string; margin_percentage: string }[];
};

export type ChinaAnalyticsBundle = {
  overview: ChinaAnalyticsOverview;
  landedCost: ChinaLandedCostAnalytics;
  suppliers: { ranking: ChinaSupplierRankingRow[] };
  categories: { categories: ChinaCategoryRow[] };
  shipments: ChinaShipmentEconomics;
};

export function canViewChinaAnalytics(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "analytics.view");
}

async function fetchSection<T>(path: string, params?: Record<string, string>): Promise<T> {
  const qs = params ? `?${new URLSearchParams(params).toString()}` : "";
  const response = await fetch(`/api/admin/analytics/china/${path}${qs}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = (await response.json()) as ApiResponse<T>;
  if (!response.ok || payload.success === false || !payload.data) {
    throw new AdminChinaAnalyticsApiError(payload.message?.trim() || "Unable to load China analytics.", response.status);
  }
  return payload.data;
}

export async function fetchChinaAnalyticsBundle(params?: { from?: string; to?: string }): Promise<ChinaAnalyticsBundle> {
  const query = Object.fromEntries(
    Object.entries(params ?? {}).filter(([, v]) => Boolean(v)).map(([k, v]) => [k, String(v)]),
  );

  const [overview, landedCost, suppliers, categories, shipments] = await Promise.all([
    fetchSection<ChinaAnalyticsOverview>("overview", query),
    fetchSection<ChinaLandedCostAnalytics>("landed-cost", query),
    fetchSection<{ ranking: ChinaSupplierRankingRow[] }>("suppliers", query),
    fetchSection<{ categories: ChinaCategoryRow[] }>("categories", query),
    fetchSection<ChinaShipmentEconomics>("shipments", query),
  ]);

  return { overview, landedCost, suppliers, categories, shipments };
}

export function formatAnalyticsMoney(value: string | number, currency = "TZS"): string {
  const n = typeof value === "string" ? Number(value) : value;
  if (!Number.isFinite(n)) return `${value} ${currency}`;
  return `${n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })} ${currency}`;
}

export function formatAnalyticsPercent(value: string | number): string {
  const n = typeof value === "string" ? Number(value) : value;
  if (!Number.isFinite(n)) return String(value);
  return `${n.toFixed(1)}%`;
}
