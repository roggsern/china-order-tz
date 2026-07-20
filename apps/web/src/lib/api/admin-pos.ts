export type PosCatalogItem = {
  product_id: string;
  product_name: string;
  product_sku: string | null;
  product_variant_id: string;
  variant_name: string | null;
  variant_sku: string | null;
  barcode: string | null;
  unit_price: string;
  currency: string;
  available_stock: number;
  in_stock: boolean;
};

export type PosCartLine = PosCatalogItem & {
  quantity: number;
  line_total: string;
};

export type PosStore = {
  id: string;
  code: string;
  name: string;
  theme_color?: string | null;
  terminals?: Array<{ id: string; code: string; name: string; is_active: boolean }>;
};

export type PosPaymentBreakdownRow = {
  code: string;
  name: string;
  amount: string;
  count: number;
  is_cash: boolean;
};

export type PosSessionSummary = {
  opening_float: string;
  cash_sales: string;
  cash_refunds: string;
  expected_cash: string;
  current_cash?: string;
  transaction_count: number;
  average_sale: string;
  total_sales: string;
  payment_breakdown: PosPaymentBreakdownRow[];
  status: string;
  closing_cash: string | null;
  variance_amount: string | null;
  variance_type: string | null;
  variance_reason: string | null;
};

export type PosSession = {
  id: string;
  status: string;
  store_id: string;
  terminal_id: string;
  admin_id?: string;
  opening_float?: string;
  expected_cash?: string | null;
  cash_sales?: string;
  closing_cash?: string | null;
  variance_amount?: string | null;
  variance_type?: string | null;
  variance_reason?: string | null;
  transaction_count?: number;
  opened_at?: string;
  closed_at?: string | null;
  store?: PosStore;
  terminal?: { id: string; code: string; name: string };
  cashier?: { id: string; name: string; email: string };
  summary?: PosSessionSummary | null;
};

export type PosPaymentMethod = {
  id: string;
  code: string;
  name: string;
  config?: { handler?: string; pos_enabled?: boolean };
};

async function parseJson<T>(response: Response): Promise<T> {
  const payload = (await response.json().catch(() => null)) as T & {
    success?: boolean;
    message?: string;
    errors?: Record<string, string[]>;
  };

  if (!response.ok) {
    const firstError = payload?.errors
      ? Object.values(payload.errors).flat()[0]
      : undefined;
    throw new Error(firstError || payload?.message || `Request failed (${response.status})`);
  }

  return payload;
}

export async function fetchPosStores(): Promise<PosStore[]> {
  const res = await fetch("/api/admin/pos/my-stores", { cache: "no-store" });
  const payload = await parseJson<{ data: PosStore[] }>(res);
  return payload.data ?? [];
}

export async function fetchPosDashboard(): Promise<{
  session: PosSession | null;
  summary: PosSessionSummary | null;
}> {
  const res = await fetch("/api/admin/pos/dashboard", { cache: "no-store" });
  const payload = await parseJson<{
    data: { session: PosSession | null; summary: PosSessionSummary | null };
  }>(res);
  return payload.data ?? { session: null, summary: null };
}

export async function fetchPosSession(): Promise<PosSession | null> {
  const res = await fetch("/api/admin/pos/sessions/current", { cache: "no-store" });
  const payload = await parseJson<{ data: PosSession | null }>(res);
  return payload.data ?? null;
}

export async function fetchPosSessions(filters: {
  store_id?: string;
  status?: string;
  from?: string;
  to?: string;
} = {}): Promise<PosSession[]> {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  const qs = params.toString();
  const res = await fetch(`/api/admin/pos/sessions${qs ? `?${qs}` : ""}`, { cache: "no-store" });
  const payload = await parseJson<{ data: PosSession[] }>(res);
  return payload.data ?? [];
}

export async function openPosSession(body: {
  store_id?: string;
  terminal_id: string;
  opening_float: number;
  notes?: string;
}): Promise<PosSession> {
  const res = await fetch("/api/admin/pos/sessions/open", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const payload = await parseJson<{ data: PosSession }>(res);
  return payload.data;
}

export async function closePosSession(body: {
  closing_cash: number;
  variance_reason?: string;
  closing_notes?: string;
}): Promise<PosSession> {
  const res = await fetch("/api/admin/pos/sessions/close", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const payload = await parseJson<{ data: PosSession }>(res);
  return payload.data;
}

export async function updatePosFloat(body: {
  opening_float: number;
  notes?: string;
}): Promise<PosSession> {
  const res = await fetch("/api/admin/pos/sessions/float", {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const payload = await parseJson<{ data: PosSession }>(res);
  return payload.data;
}

export async function searchPosCatalog(q: string): Promise<PosCatalogItem[]> {
  const params = new URLSearchParams();
  if (q.trim()) params.set("q", q.trim());
  params.set("per_page", "40");
  const res = await fetch(`/api/admin/pos/catalog?${params}`, { cache: "no-store" });
  const payload = await parseJson<{ data: PosCatalogItem[] }>(res);
  return payload.data ?? [];
}

export async function fetchPosPaymentMethods(): Promise<PosPaymentMethod[]> {
  const res = await fetch("/api/admin/pos/payment-methods", { cache: "no-store" });
  const payload = await parseJson<{ data: PosPaymentMethod[] }>(res);
  return payload.data ?? [];
}

export async function quotePosSale(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/pos/quote", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{
    data: {
      subtotal: string;
      discount_total: string;
      grand_total: string;
      promotion: { promotion_name?: string; discount_amount?: string } | null;
    };
  }>(res);
}

export async function completePosSale(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/pos/sales", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{
    data: {
      order: { id: string; order_number: string; total: string };
      receipt: PosReceipt;
      change: string | null;
      quote: { grand_total: string; discount_total: string };
      session_summary?: PosSessionSummary;
    };
  }>(res);
}

export type PosReceipt = {
  id: string;
  receipt_number: string;
  order_id: string;
  store_id: string;
  issued_at?: string | null;
  print_count?: number;
  snapshot?: Record<string, unknown>;
  qr_payload?: { payload?: string; url?: string | null } | null;
  order?: { id: string; order_number: string; total?: string; customer_name?: string | null };
  store?: { id: string; code: string; name: string; theme_color?: string | null };
};

export async function fetchPosReceipts(filters: Record<string, string | undefined> = {}): Promise<PosReceipt[]> {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  const qs = params.toString();
  const res = await fetch(`/api/admin/pos/receipts${qs ? `?${qs}` : ""}`, { cache: "no-store" });
  const payload = await parseJson<{ data: PosReceipt[] }>(res);
  return payload.data ?? [];
}

export async function fetchPosReceipt(id: string): Promise<PosReceipt> {
  const res = await fetch(`/api/admin/pos/receipts/${id}`, { cache: "no-store" });
  const payload = await parseJson<{ data: PosReceipt }>(res);
  return payload.data;
}

export async function printPosReceipt(id: string, layout = "thermal_80") {
  const res = await fetch(`/api/admin/pos/receipts/${id}/print`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ layout }),
  });
  return parseJson<{ data: { receipt: PosReceipt; html: string; layout: string } }>(res);
}

export async function reprintPosReceipt(id: string, layout = "thermal_80") {
  const res = await fetch(`/api/admin/pos/receipts/${id}/reprint`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ layout }),
  });
  return parseJson<{ data: { receipt: PosReceipt; html: string; layout: string } }>(res);
}

export function posReceiptPreviewUrl(id: string, layout = "thermal_80"): string {
  return `/api/admin/pos/receipts/${id}/preview?layout=${encodeURIComponent(layout)}`;
}

export function posReceiptPdfUrl(id: string): string {
  return `/api/admin/pos/receipts/${id}/pdf`;
}

export async function searchPosCustomers(q: string) {
  const params = new URLSearchParams();
  if (q.trim()) params.set("search", q.trim());
  params.set("per_page", "10");
  const res = await fetch(`/api/admin/customers?${params}`, { cache: "no-store" });
  const payload = await parseJson<{
    data: Array<{
      id: string;
      name?: string;
      email?: string;
      user_id?: string;
      user?: { id: string; name: string; email: string };
    }>;
  }>(res);
  return (payload.data ?? []).map((row) => ({
    id: row.user_id ?? row.user?.id ?? row.id,
    name: row.user?.name ?? row.name ?? "Customer",
    email: row.user?.email ?? row.email ?? "",
  }));
}
