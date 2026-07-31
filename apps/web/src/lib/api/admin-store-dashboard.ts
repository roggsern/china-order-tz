import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminStoreDashboardApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminStoreDashboardApiError";
  }
}

export type StoreDashboardData = {
  store: { id: string; code: string; name: string; is_active: boolean };
  period: { from: string; to: string };
  sales_summary: Record<string, number>;
  orders_count: number;
  inventory_value: number;
  inventory_units: number;
  low_stock_alerts: number;
  top_products: { product_name?: string; revenue?: number; units?: number }[];
  customers: {
    walk_in: number;
    registered: number;
    returning: number;
    new: number;
    top_customers: { customer_id?: string; revenue?: number; orders?: number }[];
  };
  profit_summary: {
    gross_revenue: number;
    net_revenue: number;
    gross_profit: number;
    margin_percentage: number;
    refund_amount: number;
  };
  team_count: number;
};

export function canViewStoreDashboard(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "stores.view");
}

export function formatStoreMoney(value: number | undefined): string {
  return new Intl.NumberFormat("en-TZ", {
    style: "currency",
    currency: "TZS",
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));
}

export async function fetchStoreDashboard(
  storeId: string,
  filters?: { from?: string; to?: string },
): Promise<StoreDashboardData> {
  const params = new URLSearchParams();
  if (filters?.from) params.set("from", filters.from);
  if (filters?.to) params.set("to", filters.to);
  const qs = params.toString() ? `?${params.toString()}` : "";

  const response = await fetch(
    `/api/admin/stores/${encodeURIComponent(storeId)}/dashboard${qs}`,
    {
      method: "GET",
      credentials: "include",
      cache: "no-store",
    },
  );

  let payload: { success?: boolean; message?: string; data?: StoreDashboardData } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminStoreDashboardApiError(
      payload.message?.trim() || "Unable to load store dashboard.",
      response.status,
    );
  }

  return payload.data;
}
