export type PosReturnReason = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
};

export type PosReturnSearchRow = {
  eligible: boolean;
  reason: string | null;
  receipt: { id: string; receipt_number: string };
  order: {
    id: string;
    order_number: string;
    total: string;
    store_id: string;
    customer_name: string;
  };
  returnable_items: Array<{
    order_item_id: string;
    product_name: string | null;
    variant_name: string | null;
    product_id?: string | null;
    product_variant_id: string | null;
    unit_price: string;
    purchased_quantity: number;
    remaining_quantity: number;
  }>;
};

export type PosReturnRecord = {
  id: string;
  return_number: string;
  return_type: string;
  refund_method?: string | null;
  refund_total?: string | null;
  receipt_snapshot?: Record<string, unknown> | null;
  order?: { order_number: string };
  cashier?: { name: string };
};

async function parseJson<T>(response: Response): Promise<T> {
  const payload = (await response.json().catch(() => null)) as T & {
    message?: string;
    errors?: Record<string, string[]>;
  };
  if (!response.ok) {
    const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : undefined;
    throw new Error(firstError || payload?.message || `Request failed (${response.status})`);
  }
  return payload;
}

export async function fetchPosReturnReasons(): Promise<PosReturnReason[]> {
  const res = await fetch("/api/admin/pos/return-reasons", { cache: "no-store" });
  const payload = await parseJson<{ data: PosReturnReason[] }>(res);
  return payload.data ?? [];
}

export async function searchPosReturns(filters: Record<string, string | undefined> = {}) {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([k, v]) => {
    if (v) params.set(k, v);
  });
  const qs = params.toString();
  const res = await fetch(`/api/admin/pos/returns/search${qs ? `?${qs}` : ""}`, { cache: "no-store" });
  const payload = await parseJson<{ data: PosReturnSearchRow[] }>(res);
  return payload.data ?? [];
}

export async function lookupPosReturnOrder(orderId: string): Promise<PosReturnSearchRow> {
  const res = await fetch(`/api/admin/pos/orders/${orderId}/return-preview`, { cache: "no-store" });
  const payload = await parseJson<{ data: PosReturnSearchRow }>(res);
  return payload.data;
}

export async function processPosReturn(body: Record<string, unknown>) {
  const res = await fetch("/api/admin/pos/returns", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return parseJson<{ data: { return: PosReturnRecord; refund: unknown } }>(res).then((p) => p.data);
}

export async function fetchPosReturns(): Promise<PosReturnRecord[]> {
  const res = await fetch("/api/admin/pos/returns", { cache: "no-store" });
  const payload = await parseJson<{ data: PosReturnRecord[] }>(res);
  return payload.data ?? [];
}
