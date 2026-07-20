export class AdminProcurementApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminProcurementApiError";
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
  throw new AdminProcurementApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export type AdminSupplier = {
  id: string;
  name: string;
  code: string;
  contact_person?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  city?: string | null;
  country?: string | null;
  payment_terms?: string | null;
  notes?: string | null;
  is_active: boolean;
  supplier_products_count?: number;
  purchase_orders_count?: number;
  supplier_products?: AdminSupplierProduct[];
};

export type AdminSupplierProduct = {
  id: string;
  product_variant_id: string;
  supplier_sku?: string | null;
  purchase_cost: string | number;
  currency: string;
  lead_time_days?: number | null;
  is_active: boolean;
  variant?: {
    id: string;
    sku?: string | null;
    name?: string | null;
    product?: { id: string; name?: string | null } | null;
  } | null;
};

export type AdminPurchaseOrderItem = {
  id: string;
  product_variant_id: string;
  quantity_ordered: number;
  quantity_received: number;
  quantity_outstanding: number;
  unit_cost: string | number;
  currency: string;
  variant?: {
    id: string;
    sku?: string | null;
    name?: string | null;
    product?: { id?: string; name?: string | null } | null;
  } | null;
};

export type AdminPurchaseOrder = {
  id: string;
  supplier_id: string;
  purchase_number: string;
  status: string;
  currency: string;
  notes?: string | null;
  ordered_at?: string | null;
  confirmed_at?: string | null;
  completed_at?: string | null;
  supplier?: {
    id: string;
    name: string;
    code?: string | null;
    country?: string | null;
  } | null;
  items?: AdminPurchaseOrderItem[];
  receiving_records?: Array<{
    id: string;
    status: string;
    received_at?: string | null;
    notes?: string | null;
  }>;
};

export async function fetchAdminSuppliers(params?: {
  search?: string;
  isActive?: boolean;
}): Promise<AdminSupplier[]> {
  const search = new URLSearchParams({ per_page: "100" });
  if (params?.search) search.set("search", params.search);
  if (params?.isActive !== undefined) search.set("is_active", params.isActive ? "1" : "0");

  const response = await fetch(`/api/admin/suppliers?${search}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminSupplier[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throwFromPayload(response, payload, "Unable to load suppliers.");
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createAdminSupplier(body: Record<string, unknown>): Promise<AdminSupplier> {
  const response = await fetch("/api/admin/suppliers", {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(body),
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminSupplier;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to create supplier.");
  }

  return payload.data;
}

export async function updateAdminSupplier(
  id: string,
  body: Record<string, unknown>,
): Promise<AdminSupplier> {
  const response = await fetch(`/api/admin/suppliers/${encodeURIComponent(id)}`, {
    method: "PUT",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(body),
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminSupplier;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to update supplier.");
  }

  return payload.data;
}

export async function upsertAdminSupplierProduct(
  supplierId: string,
  body: Record<string, unknown>,
): Promise<AdminSupplierProduct> {
  const response = await fetch(
    `/api/admin/suppliers/${encodeURIComponent(supplierId)}/products`,
    {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify(body),
      cache: "no-store",
    },
  );
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminSupplierProduct;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to save supplier product.");
  }

  return payload.data;
}

export async function fetchAdminPurchaseOrders(params?: {
  status?: string;
  supplierId?: string;
}): Promise<AdminPurchaseOrder[]> {
  const search = new URLSearchParams({ per_page: "50" });
  if (params?.status) search.set("status", params.status);
  if (params?.supplierId) search.set("supplier_id", params.supplierId);

  const response = await fetch(`/api/admin/purchase-orders?${search}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminPurchaseOrder[];
  }>(response);

  if (!response.ok || payload.success === false) {
    throwFromPayload(response, payload, "Unable to load purchase orders.");
  }

  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchAdminPurchaseOrder(id: string): Promise<AdminPurchaseOrder> {
  const response = await fetch(`/api/admin/purchase-orders/${encodeURIComponent(id)}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminPurchaseOrder;
  }>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to load purchase order.");
  }

  return payload.data;
}

export async function createAdminPurchaseOrder(
  body: Record<string, unknown>,
): Promise<AdminPurchaseOrder> {
  const response = await fetch("/api/admin/purchase-orders", {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(body),
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminPurchaseOrder;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to create purchase order.");
  }

  return payload.data;
}

export async function updateAdminPurchaseOrderStatus(
  id: string,
  status: string,
): Promise<AdminPurchaseOrder> {
  const response = await fetch(`/api/admin/purchase-orders/${encodeURIComponent(id)}/status`, {
    method: "PATCH",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ status }),
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminPurchaseOrder;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to update purchase order status.");
  }

  return payload.data;
}

export async function receiveAdminPurchaseOrder(
  id: string,
  body: {
    notes?: string;
    items: Array<{ purchase_order_item_id: string; quantity: number }>;
  },
): Promise<{ purchase_order?: AdminPurchaseOrder }> {
  const response = await fetch(`/api/admin/purchase-orders/${encodeURIComponent(id)}/receive`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(body),
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    purchase_order?: AdminPurchaseOrder;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok || payload.success === false) {
    throwFromPayload(response, payload, "Unable to receive goods.");
  }

  return payload;
}
