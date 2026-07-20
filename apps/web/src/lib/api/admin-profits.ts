export class AdminProfitsApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminProfitsApiError";
  }
}

async function parseJson<T>(response: Response): Promise<T> {
  try {
    return (await response.json()) as T;
  } catch {
    return {} as T;
  }
}

function throwFromPayload(
  response: Response,
  payload: { message?: string; errors?: Record<string, string[]> },
  fallback: string,
): never {
  const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
  throw new AdminProfitsApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export type AdminProfitDateRange = {
  from?: string;
  to?: string;
};

export type AdminProfitSummary = {
  revenue: string;
  total_cost: string;
  gross_profit: string;
  margin_percentage: string;
  orders_count: number;
  currency: string;
};

export type AdminProfitProductRow = {
  product_id: string;
  product_variant_id: string | null;
  product_name: string | null;
  variant_name: string | null;
  sku: string | null;
  units: number;
  revenue: string;
  total_cost: string;
  gross_profit: string;
  margin_percentage: string;
};

export type AdminProfitSupplierRow = {
  supplier_id: string;
  supplier_name: string | null;
  supplier_code: string | null;
  revenue: string;
  total_cost: string;
  gross_profit: string;
  margin_percentage: string;
};

export type AdminProfitChannelRow = {
  commerce_channel_code: string;
  commerce_channel_name: string;
  revenue: string;
  total_cost: string;
  gross_profit: string;
  margin_percentage: string;
};

export type AdminProfitDashboard = {
  summary: AdminProfitSummary;
  top_products: AdminProfitProductRow[];
  low_margin_products: AdminProfitProductRow[];
  suppliers: AdminProfitSupplierRow[];
  commerce_channels: AdminProfitChannelRow[];
};

export type AdminProfitOrderRow = {
  id: string;
  order_id: string;
  revenue: string;
  total_cost: string;
  gross_profit: string;
  margin_percentage: string;
  currency: string;
  calculated_at: string;
  order?: {
    id: string;
    order_number: string;
    status: string;
    commerce_channel_snapshot?: { code?: string; name?: string } | null;
    placed_at?: string | null;
    total?: string | number;
    currency?: string;
  } | null;
};

function toSearchParams(range?: AdminProfitDateRange): URLSearchParams {
  const params = new URLSearchParams();
  if (range?.from) params.set("from", range.from);
  if (range?.to) params.set("to", range.to);
  return params;
}

export async function fetchAdminProfitDashboard(
  range?: AdminProfitDateRange,
): Promise<AdminProfitDashboard> {
  const params = toSearchParams(range);
  const qs = params.toString();
  const response = await fetch(`/api/admin/profits/dashboard${qs ? `?${qs}` : ""}`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminProfitDashboard;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to load profit dashboard.");
  }

  return payload.data;
}

export async function fetchAdminProfitOrders(
  range?: AdminProfitDateRange,
): Promise<AdminProfitOrderRow[]> {
  const params = toSearchParams(range);
  params.set("per_page", "20");
  const response = await fetch(`/api/admin/profits/orders?${params.toString()}`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminProfitOrderRow[];
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load profit orders.");
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchAdminProfitProducts(
  range?: AdminProfitDateRange,
): Promise<{ top: AdminProfitProductRow[]; low_margin: AdminProfitProductRow[] }> {
  const params = toSearchParams(range);
  const qs = params.toString();
  const response = await fetch(`/api/admin/profits/products${qs ? `?${qs}` : ""}`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: { top: AdminProfitProductRow[]; low_margin: AdminProfitProductRow[] };
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data) {
    throwFromPayload(response, payload, "Unable to load product profitability.");
  }

  return payload.data;
}

export async function fetchAdminProfitSuppliers(
  range?: AdminProfitDateRange,
): Promise<AdminProfitSupplierRow[]> {
  const params = toSearchParams(range);
  const qs = params.toString();
  const response = await fetch(`/api/admin/profits/suppliers${qs ? `?${qs}` : ""}`, {
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminProfitSupplierRow[];
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load supplier profitability.");
  }

  return Array.isArray(payload.data) ? payload.data : [];
}
