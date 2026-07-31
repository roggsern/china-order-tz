import { hasAdminPermission } from "@/lib/api/admin-me";

type ApiResponse<T> = {
  success?: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
  meta?: { current_page?: number; last_page?: number; total?: number };
};

export class AdminChinaProcurementApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminChinaProcurementApiError";
  }
}

export type ChinaProcurementRequirement = {
  id: string;
  product_id: string;
  product_variant_id?: string | null;
  supplier_id?: string | null;
  quantity_required: number;
  quantity_purchased: number;
  quantity_remaining: number;
  status: string;
  status_label?: string | null;
  variant_attributes?: Record<string, unknown>;
  product?: {
    id: string;
    name: string;
    slug?: string;
    category?: { id: string; name: string; slug?: string } | null;
  } | null;
  variant?: { id: string; sku?: string | null; name?: string | null } | null;
  supplier?: { id: string; name: string; code?: string | null } | null;
  linked_orders?: {
    order_id?: string;
    order_number?: string;
    placed_at?: string | null;
    quantity: number;
  }[];
  created_at?: string | null;
  updated_at?: string | null;
};

export const PROCUREMENT_SECTIONS = [
  { id: "pending", label: "Pending Purchase", status: "pending" },
  { id: "purchasing", label: "Purchasing", status: "purchasing" },
  { id: "purchased", label: "Purchased", status: "purchased" },
  { id: "qc_pending", label: "QC Pending", status: "qc_pending" },
  { id: "completed", label: "Completed", status: "completed" },
] as const;

export function canViewChinaProcurement(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "procurement.view");
}

export function canManageChinaProcurement(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "procurement.update");
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
  payload: ApiResponse<unknown>,
  fallback: string,
): never {
  const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
  throw new AdminChinaProcurementApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export async function fetchChinaProcurementRequirements(params?: {
  status?: string;
  supplierId?: string;
  productId?: string;
  productVariantId?: string;
  categoryId?: string;
  from?: string;
  to?: string;
  page?: number;
  perPage?: number;
}): Promise<{ items: ChinaProcurementRequirement[]; total: number; lastPage: number; page: number }> {
  const search = new URLSearchParams();
  if (params?.status) search.set("status", params.status);
  if (params?.supplierId) search.set("supplier_id", params.supplierId);
  if (params?.productId) search.set("product_id", params.productId);
  if (params?.productVariantId) search.set("product_variant_id", params.productVariantId);
  if (params?.categoryId) search.set("category_id", params.categoryId);
  if (params?.from) search.set("from", params.from);
  if (params?.to) search.set("to", params.to);
  if (params?.page) search.set("page", String(params.page));
  if (params?.perPage) search.set("per_page", String(params.perPage));

  const response = await fetch(`/api/admin/china/procurement?${search}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<ApiResponse<ChinaProcurementRequirement[]>>(response);

  if (!response.ok || payload.success === false) {
    throwFromPayload(response, payload, "Unable to load procurement board.");
  }

  return {
    items: Array.isArray(payload.data) ? payload.data : [],
    total: payload.meta?.total ?? 0,
    lastPage: payload.meta?.last_page ?? 1,
    page: payload.meta?.current_page ?? 1,
  };
}

export async function fetchChinaProcurementRequirement(id: string): Promise<ChinaProcurementRequirement> {
  const response = await fetch(`/api/admin/china/procurement/${encodeURIComponent(id)}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<ApiResponse<ChinaProcurementRequirement>>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to load procurement requirement.");
  }

  return payload.data;
}

export async function markChinaProcurementPurchased(
  id: string,
  quantityPurchased: number,
): Promise<ChinaProcurementRequirement> {
  const response = await fetch(`/api/admin/china/procurement/${encodeURIComponent(id)}/mark-purchased`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ quantity_purchased: quantityPurchased }),
    cache: "no-store",
  });
  const payload = await parseJson<ApiResponse<ChinaProcurementRequirement>>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to mark purchased quantity.");
  }

  return payload.data;
}

export async function startChinaProcurementQc(id: string): Promise<ChinaProcurementRequirement> {
  const response = await fetch(`/api/admin/china/procurement/${encodeURIComponent(id)}/start-qc`, {
    method: "POST",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<ApiResponse<ChinaProcurementRequirement>>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to start QC.");
  }

  return payload.data;
}

export async function completeChinaProcurement(id: string): Promise<ChinaProcurementRequirement> {
  const response = await fetch(`/api/admin/china/procurement/${encodeURIComponent(id)}/complete`, {
    method: "POST",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<ApiResponse<ChinaProcurementRequirement>>(response);

  if (!response.ok || !payload.data?.id) {
    throwFromPayload(response, payload, "Unable to complete procurement requirement.");
  }

  return payload.data;
}
