import type { Order } from "@/lib/types/order";
import { type AdminOrdersListMeta } from "@/lib/admin/admin-orders-pagination";

export type AdminOrdersFetchResult = {
  ok: boolean;
  status: number | null;
  unauthenticated: boolean;
  orders: Order[];
  meta: AdminOrdersListMeta;
};

export type AdminOrdersBootstrapState = {
  hydrated: boolean;
  orders: Order[];
  meta: AdminOrdersListMeta;
};

export function shouldBootstrapAdminOrders(input: {
  isReady: boolean;
  isAuthenticated: boolean;
}): boolean {
  return input.isReady && input.isAuthenticated;
}

export function isAdminOrdersAuthFailureStatus(status: number | null): boolean {
  return status === 401 || status === 403;
}

export function applyAdminOrdersFetchResult(
  current: AdminOrdersBootstrapState,
  result: AdminOrdersFetchResult,
): AdminOrdersBootstrapState & { applied: boolean; authFailure: boolean } {
  if (!result.ok) {
    return {
      hydrated: current.hydrated,
      orders: current.orders,
      meta: current.meta,
      applied: false,
      authFailure: result.unauthenticated,
    };
  }

  return {
    hydrated: true,
    orders: result.orders,
    meta: result.meta,
    applied: true,
    authFailure: false,
  };
}
