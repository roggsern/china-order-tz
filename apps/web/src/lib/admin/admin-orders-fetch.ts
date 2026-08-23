import type { Order } from "@/lib/types/order";

export type AdminOrdersFetchResult = {
  ok: boolean;
  status: number | null;
  unauthenticated: boolean;
  orders: Order[];
};

export type AdminOrdersBootstrapState = {
  hydrated: boolean;
  orders: Order[];
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
      applied: false,
      authFailure: result.unauthenticated,
    };
  }

  return {
    hydrated: true,
    orders: result.orders,
    applied: true,
    authFailure: false,
  };
}
