import type { AdminWarehouseJob } from "@/lib/api/admin-warehouse";

export const PICK_LIST_CREATE_STATUSES = new Set(["pending", "picking"]);
export const PACKING_CREATE_STATUSES = new Set(["picked", "packing"]);

export type WarehouseOperationalMaps = {
  pickListByJobId: Record<string, string>;
  packingByJobId: Record<string, string>;
};

export function buildWarehouseOperationalMaps(
  pickLists: { id: string; warehouse_job_id: string }[],
  packingRecords: { id: string; warehouse_job_id: string }[],
): WarehouseOperationalMaps {
  const pickListByJobId: Record<string, string> = {};
  for (const pickList of pickLists) {
    pickListByJobId[pickList.warehouse_job_id] = pickList.id;
  }

  const packingByJobId: Record<string, string> = {};
  for (const record of packingRecords) {
    packingByJobId[record.warehouse_job_id] = record.id;
  }

  return { pickListByJobId, packingByJobId };
}

export function isTerminalWarehouseJobStatus(status: string): boolean {
  return status === "cancelled" || status === "ready_to_ship";
}

export function canCreatePickListForJob(
  job: Pick<AdminWarehouseJob, "id" | "status">,
  maps: WarehouseOperationalMaps,
  canManage: boolean,
): boolean {
  if (!canManage) return false;
  if (maps.pickListByJobId[job.id]) return false;
  if (isTerminalWarehouseJobStatus(job.status)) return false;
  return PICK_LIST_CREATE_STATUSES.has(job.status);
}

export function canOpenPickListForJob(
  job: Pick<AdminWarehouseJob, "id">,
  maps: WarehouseOperationalMaps,
): boolean {
  return Boolean(maps.pickListByJobId[job.id]);
}

export function getPickListIdForJob(
  job: Pick<AdminWarehouseJob, "id">,
  maps: WarehouseOperationalMaps,
): string | null {
  return maps.pickListByJobId[job.id] ?? null;
}

export function canCreatePackingForJob(
  job: Pick<AdminWarehouseJob, "id" | "status">,
  maps: WarehouseOperationalMaps,
  canManage: boolean,
): boolean {
  if (!canManage) return false;
  if (maps.packingByJobId[job.id]) return false;
  if (isTerminalWarehouseJobStatus(job.status)) return false;
  return PACKING_CREATE_STATUSES.has(job.status);
}

export function canOpenPackingForJob(
  job: Pick<AdminWarehouseJob, "id">,
  maps: WarehouseOperationalMaps,
): boolean {
  return Boolean(maps.packingByJobId[job.id]);
}

export function getPackingIdForJob(
  job: Pick<AdminWarehouseJob, "id">,
  maps: WarehouseOperationalMaps,
): string | null {
  return maps.packingByJobId[job.id] ?? null;
}
