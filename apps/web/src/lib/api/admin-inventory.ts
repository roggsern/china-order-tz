export class AdminInventoryApiError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "AdminInventoryApiError";
  }
}

async function parseJson<T>(res: Response): Promise<T> {
  const payload = (await res.json().catch(() => ({}))) as T & { message?: string };
  if (!res.ok) {
    throw new AdminInventoryApiError(payload.message ?? `Inventory request failed (${res.status})`);
  }
  return payload;
}

export type InventoryDashboard = {
  sku_count: number;
  sellable_units: number;
  damaged_units: number;
  inspection_units: number;
  low_stock_skus: number;
  inventory_value: number;
  open_counts: number;
};

export type InventoryStockRow = {
  id: string;
  product_variant_id: string;
  sku?: string;
  product_name?: string;
  store_name?: string;
  on_hand: number;
  available: number;
  damaged: number;
  inspection: number;
  reorder_level: number;
  needs_reorder: boolean;
};

export type InventoryMovement = {
  id: string;
  movement_type: string;
  sku?: string;
  product_name?: string;
  store_name?: string;
  quantity_before: number;
  quantity_change: number;
  quantity_after: number;
  reason?: string | null;
  created_at?: string;
};

export type InventoryCountSession = {
  id: string;
  count_number: string;
  status: string;
  scope: string;
  store?: { id: string; code: string; name: string } | null;
  lines?: Array<{
    id: string;
    sku?: string;
    product_name?: string;
    system_quantity: number;
    counted_quantity?: number | null;
    difference?: number | null;
    reason?: string | null;
  }>;
};

function qs(storeId?: string) {
  const p = new URLSearchParams();
  if (storeId) p.set("store_id", storeId);
  return p.toString() ? `?${p}` : "";
}

export async function fetchInventoryDashboard(storeId?: string) {
  const res = await fetch(`/api/admin/inventory${qs(storeId)}`, { cache: "no-store" });
  const payload = await parseJson<{ data: InventoryDashboard }>(res);
  return payload.data;
}

export async function fetchInventoryStock(storeId?: string) {
  const res = await fetch(`/api/admin/inventory/stock${qs(storeId)}`, { cache: "no-store" });
  const payload = await parseJson<{ data: InventoryStockRow[] }>(res);
  return payload.data ?? [];
}

export async function fetchInventoryMovements(storeId?: string) {
  const res = await fetch(`/api/admin/inventory/movements${qs(storeId)}`, { cache: "no-store" });
  const payload = await parseJson<{ data: InventoryMovement[] }>(res);
  return payload.data ?? [];
}

export async function fetchInventoryValuation(storeId?: string) {
  const res = await fetch(`/api/admin/inventory/valuation${qs(storeId)}`, { cache: "no-store" });
  const payload = await parseJson<{
    data: { summary: Record<string, number>; rows: Array<Record<string, unknown>> };
  }>(res);
  return payload.data;
}

export async function fetchInventoryLowStock(storeId?: string) {
  const res = await fetch(`/api/admin/inventory/low-stock${qs(storeId)}`, { cache: "no-store" });
  const payload = await parseJson<{ data: Array<Record<string, unknown>> }>(res);
  return payload.data ?? [];
}

export async function createInventoryAdjustment(body: {
  store_id: string;
  product_variant_id: string;
  quantity_change: number;
  reason: string;
  kind?: string;
}) {
  const res = await fetch("/api/admin/inventory/adjustments", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: InventoryMovement }>(res);
}

export async function fetchInventoryCounts(storeId?: string) {
  const res = await fetch(`/api/admin/inventory/counts${qs(storeId)}`, { cache: "no-store" });
  const payload = await parseJson<{ data: InventoryCountSession[] }>(res);
  return payload.data ?? [];
}

export async function createInventoryCount(body: {
  store_id: string;
  scope?: string;
  notes?: string;
  variant_ids?: string[];
}) {
  const res = await fetch("/api/admin/inventory/counts", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: InventoryCountSession }>(res);
}

export async function recordInventoryCountLines(
  countId: string,
  lines: Array<{ line_id: string; counted_quantity: number; reason?: string }>,
) {
  const res = await fetch(`/api/admin/inventory/counts/${countId}/lines`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ lines }),
  });
  return parseJson<{ data: InventoryCountSession }>(res);
}

export async function submitInventoryCount(countId: string) {
  const res = await fetch(`/api/admin/inventory/counts/${countId}/submit`, { method: "POST" });
  return parseJson<{ data: InventoryCountSession }>(res);
}

export async function approveInventoryCount(countId: string, reason?: string) {
  const res = await fetch(`/api/admin/inventory/counts/${countId}/approve`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ reason }),
  });
  return parseJson<{ data: InventoryCountSession }>(res);
}
