export const ADMIN_REPORT_TYPES = [
  "sales",
  "orders",
  "payments",
  "warehouse",
  "shipments",
  "returns",
  "notifications",
] as const;

export type AdminReportType = (typeof ADMIN_REPORT_TYPES)[number];

export type AdminReportExportFormat = "csv" | "xlsx";

export type AdminReportPeriod = {
  from: string;
  to: string;
};

export type AdminReportingDateRange = {
  from?: string;
  to?: string;
};

export type AdminReportingSalesMetrics = {
  total_revenue: number;
  paid_revenue: number;
  pending_revenue: number;
  refunded_revenue: number;
};

export type AdminReportingOrdersMetrics = {
  orders_today: number;
  orders_this_week: number;
  orders_this_month: number;
  total_orders: number;
  completed_orders: number;
  cancelled_orders: number;
};

export type AdminReportingCustomersMetrics = {
  total_customers: number;
  new_customers: number;
  returning_customers: number;
};

export type AdminReportingWarehouseMetrics = {
  picking: number;
  packing: number;
  ready_to_ship: number;
};

export type AdminReportingShipmentsMetrics = {
  created: number;
  in_transit: number;
  delivered: number;
};

export type AdminReportingReturnsMetrics = {
  open: number;
  approved: number;
  completed: number;
  refunded_amount: number;
};

export type AdminReportingNotificationsMetrics = {
  sent: number;
  failed: number;
  pending: number;
};

export type AdminReportingDailyRevenuePoint = {
  date: string;
  revenue: number;
};

export type AdminReportingCountPoint = {
  date: string;
  count: number;
};

export type AdminReportingStatusPoint = {
  status: string;
  count: number;
};

export type AdminReportingTopProduct = {
  product_id: string | null;
  name: string;
  quantity: number;
  revenue: number;
};

export type AdminReportingActivityItem = {
  id: string;
  event_type?: string | null;
  description?: string | null;
  actor_type?: string | null;
  created_at?: string | null;
};

export type AdminCommandCenterOverview = {
  orders_today: number;
  revenue_today: number;
  paid_orders_today: number;
  pending_actions: number;
  customers_total: number;
  new_customers: number;
};

export type AdminCommandCenterOperations = {
  fulfillment_queue: {
    total: number;
    china: number;
    local: number;
  };
  warehouse: AdminReportingWarehouseMetrics;
  shipments: AdminReportingShipmentsMetrics;
  open_returns: number;
};

export type AdminCommandCenterChinaPipeline = {
  procurement: number;
  qc_pending: number;
  warehouse_packing: number;
  export_ready: number;
  shipment_pending: number;
  arrived_tanzania: number;
  awaiting_receiving_choice: number;
  handover_pending: number;
};

export type AdminCommandCenterTzLocalPipeline = {
  pending: number;
  processing: number;
  ready_for_shipping: number;
  shipped: number;
  ready_for_completion: number;
};

export type AdminCommandCenterAttentionItem = {
  key: string;
  label: string;
  count: number;
  severity: "normal" | "medium" | "high";
  href: string;
};

export type AdminCommandCenterStoreSummary = {
  active_stores: number;
  orders_today_by_store: Array<{
    store_id: string | null;
    store_name: string | null;
    orders_today: number;
  }>;
};

export type AdminStorefrontTrafficMetrics = {
  reference_date: string;
  visitors_today: number;
  sessions_today: number;
  new_visitors: number;
  returning_visitors: number;
  growth: {
    visitors_change: number;
    visitors_change_percent: number;
    sessions_change: number;
    sessions_change_percent: number;
  };
  top_pages: Array<{ path: string; views: number }>;
  top_products: Array<{ product_id: string; name: string; views: number }>;
  top_searches: Array<{ query: string; count: number }>;
};

export type AdminStorefrontConversionMetrics = {
  funnel: {
    visitors: number;
    product_viewers: number;
    cart_users: number;
    checkout_users: number;
    buyers: number;
  };
  conversion_rates: {
    visitor_to_product_view: number;
    product_view_to_cart: number;
    cart_to_checkout: number;
    checkout_to_purchase: number;
    visitor_to_purchase: number;
  };
  attribution: {
    orders_with_journey: number;
    attributed_buyers: number;
    first_touch_pages: Array<{ path: string; visitors: number; orders: number }>;
  };
  product_insights: Array<{
    product_id: string;
    name: string;
    views: number;
    cart_additions: number;
    orders: number;
    conversion_rate: number;
  }>;
};

export type AdminGrowthIntelligenceAlert = {
  type: string;
  category: "warning" | "opportunity";
  severity: "HIGH" | "MEDIUM" | "LOW";
  title: string;
  message: string;
};

export type AdminGrowthIntelligenceMetrics = {
  health_status: "healthy" | "watch" | "at_risk";
  health_summary: {
    visitors: number;
    buyers: number;
    visitor_to_purchase: number;
    visitors_change_percent: number;
    conversion_change_points: number;
    warning_count: number;
    opportunity_count: number;
    high_severity_count: number;
  };
  growth_comparisons: {
    visitors_current: number;
    visitors_previous: number;
    visitors_change_percent: number;
    buyers_current: number;
    buyers_previous: number;
    buyers_change_percent: number;
    conversion_rate_current: number;
    conversion_rate_previous: number;
    conversion_change_points: number;
  };
  alerts: AdminGrowthIntelligenceAlert[];
};

export type AdminReportingDashboard = {
  period: AdminReportPeriod;
  sales: AdminReportingSalesMetrics;
  orders: AdminReportingOrdersMetrics;
  customers: AdminReportingCustomersMetrics;
  warehouse: AdminReportingWarehouseMetrics;
  shipments: AdminReportingShipmentsMetrics;
  returns: AdminReportingReturnsMetrics;
  notifications: AdminReportingNotificationsMetrics;
  charts: {
    daily_revenue: AdminReportingDailyRevenuePoint[];
    orders_trend: AdminReportingCountPoint[];
    payment_status: AdminReportingStatusPoint[];
    warehouse_status: AdminReportingStatusPoint[];
    shipment_status: AdminReportingStatusPoint[];
    returns_trend: AdminReportingCountPoint[];
  };
  top_products: AdminReportingTopProduct[];
  recent_activity: AdminReportingActivityItem[];
  overview?: AdminCommandCenterOverview;
  operations?: AdminCommandCenterOperations;
  china_pipeline?: AdminCommandCenterChinaPipeline;
  tz_local?: AdminCommandCenterTzLocalPipeline;
  attention_items?: AdminCommandCenterAttentionItem[];
  store_summary?: AdminCommandCenterStoreSummary;
  storefront_traffic?: AdminStorefrontTrafficMetrics;
  storefront_conversion?: AdminStorefrontConversionMetrics;
  growth_intelligence?: AdminGrowthIntelligenceMetrics;
  section_errors?: Partial<Record<string, string>>;
};

export type AdminAlertSource = "operational" | "growth";

export type AdminAlertSeverity = "HIGH" | "MEDIUM" | "LOW";

export type AdminAlert = {
  severity: AdminAlertSeverity;
  title: string;
  message: string;
  source: AdminAlertSource;
  created_at: string;
  key?: string;
  href?: string;
  type?: string;
  category?: string;
};

export type AdminAlertsPayload = {
  period: AdminReportPeriod;
  generated_at: string;
  counts: {
    operational: number;
    growth: number;
    total: number;
  };
  alerts: AdminAlert[];
};

export type AdminReportPayload = {
  type: AdminReportType | string;
  period: AdminReportPeriod;
  summary: Record<string, unknown>;
  rows: Array<Record<string, unknown>>;
  columns: string[];
};

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export class AdminReportingApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminReportingApiError";
  }
}

export const ADMIN_REPORT_TYPE_LABELS: Record<AdminReportType, string> = {
  sales: "Sales",
  orders: "Orders",
  payments: "Payments",
  warehouse: "Warehouse",
  shipments: "Shipments",
  returns: "Returns",
  notifications: "Notifications",
};

function formatError(payload: ApiSuccessResponse<unknown>, fallback: string): string {
  if (payload.message?.trim()) return payload.message.trim();
  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) return first.trim();
  }
  return fallback;
}

function toSearchParams(range?: AdminReportingDateRange): string {
  const search = new URLSearchParams();
  if (range?.from?.trim()) search.set("from", range.from.trim());
  if (range?.to?.trim()) search.set("to", range.to.trim());
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

async function adminFetchJson<T>(path: string, fallback: string): Promise<T> {
  const response = await fetch(path, {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = (await response.json().catch(() => null)) as ApiSuccessResponse<T> | null;
  if (!response.ok || !payload || payload.success === false) {
    throw new AdminReportingApiError(
      formatError(payload ?? {}, fallback),
      response.status,
    );
  }
  if (payload.data === undefined) {
    throw new AdminReportingApiError(fallback, response.status);
  }
  return payload.data;
}

export function isAdminReportType(value: string): value is AdminReportType {
  return (ADMIN_REPORT_TYPES as readonly string[]).includes(value);
}

export function adminReportExportUrl(
  type: AdminReportType | string,
  options?: AdminReportingDateRange & { format?: AdminReportExportFormat },
): string {
  const search = new URLSearchParams();
  search.set("format", options?.format ?? "csv");
  if (options?.from?.trim()) search.set("from", options.from.trim());
  if (options?.to?.trim()) search.set("to", options.to.trim());
  return `/api/admin/reports/${encodeURIComponent(type)}/export?${search.toString()}`;
}

export async function fetchAdminReportingDashboard(
  range?: AdminReportingDateRange,
): Promise<AdminReportingDashboard> {
  return adminFetchJson<AdminReportingDashboard>(
    `/api/admin/dashboard${toSearchParams(range)}`,
    "Unable to load reporting dashboard.",
  );
}

export async function fetchAdminAlerts(range?: AdminReportingDateRange): Promise<AdminAlertsPayload> {
  return adminFetchJson<AdminAlertsPayload>(
    `/api/admin/alerts${toSearchParams(range)}`,
    "Unable to load admin alerts.",
  );
}

export async function fetchAdminReport(
  type: AdminReportType | string,
  range?: AdminReportingDateRange,
): Promise<AdminReportPayload> {
  if (!isAdminReportType(type)) {
    throw new AdminReportingApiError("Unknown report type.");
  }
  return adminFetchJson<AdminReportPayload>(
    `/api/admin/reports/${encodeURIComponent(type)}${toSearchParams(range)}`,
    `Unable to load ${type} report.`,
  );
}

function filenameFromDisposition(header: string | null, fallback: string): string {
  if (!header) return fallback;
  const utfMatch = /filename\*=UTF-8''([^;]+)/i.exec(header);
  if (utfMatch?.[1]) {
    try {
      return decodeURIComponent(utfMatch[1].trim());
    } catch {
      return utfMatch[1].trim();
    }
  }
  const plainMatch = /filename="?([^";]+)"?/i.exec(header);
  return plainMatch?.[1]?.trim() || fallback;
}

export async function downloadAdminReport(
  type: AdminReportType | string,
  format: AdminReportExportFormat,
  range?: AdminReportingDateRange,
): Promise<void> {
  if (!isAdminReportType(type)) {
    throw new AdminReportingApiError("Unknown report type.");
  }

  const url = adminReportExportUrl(type, { ...range, format });
  const response = await fetch(url, {
    method: "GET",
    cache: "no-store",
  });

  const contentType = response.headers.get("content-type") || "";
  if (!response.ok || contentType.includes("application/json")) {
    const payload = (await response.json().catch(() => null)) as ApiSuccessResponse<unknown> | null;
    throw new AdminReportingApiError(
      formatError(payload ?? {}, `Unable to export ${type} report.`),
      response.status,
    );
  }

  const blob = await response.blob();
  const filename = filenameFromDisposition(
    response.headers.get("content-disposition"),
    `${type}-report.${format}`,
  );

  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = objectUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(objectUrl);
}
