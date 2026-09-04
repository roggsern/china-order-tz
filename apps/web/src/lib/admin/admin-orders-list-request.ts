import {
  type AdminOrdersListQuery,
} from "@/lib/admin/admin-orders-pagination";

const inFlightQueryCounts = new Map<string, number>();

export function serializeAdminOrdersListQuery(query: AdminOrdersListQuery): string {
  return [
    String(query.page > 0 ? query.page : 1),
    String(query.perPage ?? 20),
    query.status && query.status !== "all" ? query.status : "all",
    query.search?.trim() ?? "",
    query.source && query.source !== "all" ? query.source : "all",
  ].join("|");
}

export function shouldApplyAdminOrdersListResponse(
  requestGeneration: number,
  latestGeneration: number,
): boolean {
  return requestGeneration === latestGeneration;
}

export function shouldApplyAdminOrdersPollSnapshot(args: {
  requestedQueryKey: string;
  activeQueryKey: string;
}): boolean {
  return args.requestedQueryKey === args.activeQueryKey;
}

export function markAdminOrdersListQueryInFlight(queryKey: string): void {
  inFlightQueryCounts.set(queryKey, (inFlightQueryCounts.get(queryKey) ?? 0) + 1);
}

export function clearAdminOrdersListQueryInFlight(queryKey: string): void {
  const next = (inFlightQueryCounts.get(queryKey) ?? 1) - 1;
  if (next <= 0) {
    inFlightQueryCounts.delete(queryKey);
    return;
  }

  inFlightQueryCounts.set(queryKey, next);
}

export function isAdminOrdersListQueryInFlight(queryKey: string): boolean {
  return (inFlightQueryCounts.get(queryKey) ?? 0) > 0;
}

export function resetAdminOrdersListInFlight(): void {
  inFlightQueryCounts.clear();
}

export function shouldStartAdminOrdersPoll(queryKey: string): boolean {
  return !isAdminOrdersListQueryInFlight(queryKey);
}
