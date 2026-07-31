import { hasAdminPermission } from "@/lib/api/admin-me";

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  meta?: Record<string, unknown>;
  errors?: Record<string, string[]>;
};

export class AdminWarehouseOpsApiError extends Error {
  constructor(message: string, public readonly statusCode?: number) {
    super(message);
    this.name = "AdminWarehouseOpsApiError";
  }
}

export type WarehousePickListLine = {
  id: string;
  product_name: string;
  sku?: string | null;
  quantity: number;
  picked_quantity: number;
  status: string;
  status_label?: string | null;
  warehouse_bin?: { code?: string; name?: string; zone?: { code?: string; name?: string } | null } | null;
};

export type WarehousePickList = {
  id: string;
  warehouse_job_id: string;
  order_id: string;
  status: string;
  status_label?: string | null;
  order?: { order_number?: string; customer?: { name?: string } | null } | null;
  warehouse_job?: { job_number?: string; status?: string } | null;
  lines?: WarehousePickListLine[];
};

export type WarehousePackingRecord = {
  id: string;
  warehouse_job_id: string;
  status: string;
  status_label?: string | null;
  package_status?: string | null;
  notes?: string | null;
  warehouse_job?: { job_number?: string; order?: { order_number?: string } | null } | null;
  lines?: { id: string; quantity: number; packed_quantity: number }[];
};

export type WarehouseTransfer = {
  id: string;
  transfer_number: string;
  status: string;
  status_label?: string | null;
  from_facility?: { code?: string; name?: string } | null;
  to_facility?: { code?: string; name?: string } | null;
  lines?: { product_variant_id: string; quantity: number; product_variant?: { sku?: string } | null }[];
};

export type WarehouseFacility = {
  id: string;
  code: string;
  name: string;
  inventory_warehouse_code?: string | null;
};

export type WarehouseZone = {
  id: string;
  code: string;
  name: string;
  facility_id: string;
};

export type WarehouseBin = {
  id: string;
  code: string;
  name: string;
  zone_id: string;
};

async function opsFetch<T>(path: string, init: RequestInit, fallback: string): Promise<T> {
  const response = await fetch(path, { ...init, cache: "no-store" });
  const payload = (await response.json()) as ApiSuccessResponse<T>;
  if (!response.ok || payload.success === false) {
    throw new AdminWarehouseOpsApiError(payload.message?.trim() || fallback, response.status);
  }
  return payload.data as T;
}

function unwrapPaginator<T>(payload: T[] | { data?: T[] } | { data?: { data?: T[] } } | undefined): T[] {
  if (Array.isArray(payload)) return payload;
  if (!payload || typeof payload !== "object") return [];
  const level1 = (payload as { data?: T[] | { data?: T[] } }).data;
  if (Array.isArray(level1)) return level1;
  if (level1 && typeof level1 === "object" && Array.isArray((level1 as { data?: T[] }).data)) {
    return (level1 as { data?: T[] }).data ?? [];
  }
  return [];
}

export function canViewWarehouse(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "warehouse.view") || hasAdminPermission(permissions, "warehouse.jobs.view");
}

export function canManageWarehouse(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "warehouse.manage") || hasAdminPermission(permissions, "warehouse.jobs.update");
}

export function canTransferWarehouse(permissions?: string[] | null): boolean {
  return hasAdminPermission(permissions, "warehouse.transfer") || hasAdminPermission(permissions, "inventory.transfer");
}

export async function fetchWarehousePickLists(status?: string): Promise<WarehousePickList[]> {
  const qs = status ? `?status=${encodeURIComponent(status)}` : "";
  const data = await opsFetch<WarehousePickList[] | { data: WarehousePickList[] }>(
    `/api/admin/warehouse/pick-lists${qs}`,
    { headers: { Accept: "application/json" } },
    "Unable to load pick lists.",
  );
  return unwrapPaginator(data);
}

export async function fetchWarehousePickList(id: string): Promise<WarehousePickList> {
  return opsFetch(`/api/admin/warehouse/pick-lists/${encodeURIComponent(id)}`, {
    headers: { Accept: "application/json" },
  }, "Unable to load pick list.");
}

export async function createWarehousePickList(warehouseJobId: string): Promise<WarehousePickList> {
  return opsFetch(`/api/admin/warehouse/pick-lists`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ warehouse_job_id: warehouseJobId }),
  }, "Unable to create pick list.");
}

export async function startWarehousePickList(id: string): Promise<WarehousePickList> {
  return opsFetch(`/api/admin/warehouse/pick-lists/${encodeURIComponent(id)}/start`, {
    method: "POST",
    headers: { Accept: "application/json" },
  }, "Unable to start pick list.");
}

export async function completeWarehousePickList(id: string): Promise<WarehousePickList> {
  return opsFetch(`/api/admin/warehouse/pick-lists/${encodeURIComponent(id)}/complete`, {
    method: "POST",
    headers: { Accept: "application/json" },
  }, "Unable to complete pick list.");
}

export async function updateWarehousePickLine(
  pickListId: string,
  lineId: string,
  pickedQuantity: number,
): Promise<void> {
  await opsFetch(`/api/admin/warehouse/pick-lists/${encodeURIComponent(pickListId)}/lines/${encodeURIComponent(lineId)}`, {
    method: "PATCH",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ picked_quantity: pickedQuantity }),
  }, "Unable to update pick line.");
}

export async function fetchWarehousePacking(status?: string): Promise<WarehousePackingRecord[]> {
  const qs = status ? `?status=${encodeURIComponent(status)}` : "";
  const data = await opsFetch<WarehousePackingRecord[] | { data: WarehousePackingRecord[] }>(
    `/api/admin/warehouse/packing${qs}`,
    { headers: { Accept: "application/json" } },
    "Unable to load packing queue.",
  );
  return unwrapPaginator(data);
}

export async function createWarehousePacking(warehouseJobId: string): Promise<WarehousePackingRecord> {
  return opsFetch(`/api/admin/warehouse/packing`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ warehouse_job_id: warehouseJobId }),
  }, "Unable to create packing record.");
}

export async function startWarehousePacking(id: string): Promise<WarehousePackingRecord> {
  return opsFetch(`/api/admin/warehouse/packing/${encodeURIComponent(id)}/start`, {
    method: "POST",
    headers: { Accept: "application/json" },
  }, "Unable to start packing.");
}

export async function completeWarehousePacking(id: string): Promise<WarehousePackingRecord> {
  return opsFetch(`/api/admin/warehouse/packing/${encodeURIComponent(id)}/complete`, {
    method: "POST",
    headers: { Accept: "application/json" },
  }, "Unable to complete packing.");
}

export async function updateWarehousePackingLine(
  packingId: string,
  lineId: string,
  packedQuantity: number,
): Promise<void> {
  await opsFetch(`/api/admin/warehouse/packing/${encodeURIComponent(packingId)}/lines/${encodeURIComponent(lineId)}`, {
    method: "PATCH",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ packed_quantity: packedQuantity }),
  }, "Unable to update packing line.");
}

export async function fetchWarehouseTransfers(status?: string): Promise<WarehouseTransfer[]> {
  const qs = status ? `?status=${encodeURIComponent(status)}` : "";
  const data = await opsFetch<WarehouseTransfer[] | { data: WarehouseTransfer[] }>(
    `/api/admin/warehouse/transfers${qs}`,
    { headers: { Accept: "application/json" } },
    "Unable to load transfers.",
  );
  return unwrapPaginator(data);
}

export async function approveWarehouseTransfer(id: string): Promise<WarehouseTransfer> {
  return opsFetch(`/api/admin/warehouse/transfers/${encodeURIComponent(id)}/approve`, {
    method: "POST",
    headers: { Accept: "application/json" },
  }, "Unable to approve transfer.");
}

export async function completeWarehouseTransfer(id: string): Promise<WarehouseTransfer> {
  return opsFetch(`/api/admin/warehouse/transfers/${encodeURIComponent(id)}/complete`, {
    method: "POST",
    headers: { Accept: "application/json" },
  }, "Unable to complete transfer.");
}

export async function fetchWarehouseFacilities(): Promise<WarehouseFacility[]> {
  const payload = await opsFetch<{ data?: WarehouseFacility[] } | WarehouseFacility[]>(
    "/api/admin/warehouse/locations/facilities",
    { headers: { Accept: "application/json" } },
    "Unable to load facilities.",
  );
  if (Array.isArray(payload)) return payload;
  return unwrapPaginator(payload);
}

export async function fetchWarehouseZones(facilityId?: string): Promise<WarehouseZone[]> {
  const qs = facilityId ? `?facility_id=${encodeURIComponent(facilityId)}` : "";
  const payload = await opsFetch<{ data?: WarehouseZone[] } | WarehouseZone[]>(
    `/api/admin/warehouse/locations/zones${qs}`,
    { headers: { Accept: "application/json" } },
    "Unable to load zones.",
  );
  if (Array.isArray(payload)) return payload;
  return unwrapPaginator(payload);
}

export async function fetchWarehouseBins(zoneId?: string): Promise<WarehouseBin[]> {
  const qs = zoneId ? `?zone_id=${encodeURIComponent(zoneId)}` : "";
  const payload = await opsFetch<{ data?: WarehouseBin[] } | WarehouseBin[]>(
    `/api/admin/warehouse/locations/bins${qs}`,
    { headers: { Accept: "application/json" } },
    "Unable to load bins.",
  );
  if (Array.isArray(payload)) return payload;
  return unwrapPaginator(payload);
}
