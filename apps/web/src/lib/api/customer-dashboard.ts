import { getCustomerApiToken } from "@/lib/api/customer-auth";

export type CustomerDashboardSummary = {
  activeOrders: number;
  inTransitOrders: number;
  pendingPayments: number;
  completedOrders: number;
};

export class CustomerDashboardApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerDashboardApiError";
  }
}

/**
 * Lightweight customer dashboard summary for header badges.
 * Uses existing GET /api/v1/dashboard — do not issue per-order list queries in the header.
 */
export async function fetchCustomerDashboardSummary(): Promise<CustomerDashboardSummary> {
  const token = getCustomerApiToken();

  if (!token) {
    throw new CustomerDashboardApiError("Authentication is required.", 401);
  }

  const response = await fetch("/api/dashboard", {
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    message?: string;
    data?: {
      summary?: {
        active_orders?: number;
        in_transit_orders?: number;
        pending_payments?: number;
        completed_orders?: number;
      };
    };
  };

  if (!response.ok || !payload.success || !payload.data?.summary) {
    throw new CustomerDashboardApiError(
      payload.message ?? "Unable to load dashboard summary.",
      response.status,
    );
  }

  const summary = payload.data.summary;

  return {
    activeOrders: Number(summary.active_orders ?? 0) || 0,
    inTransitOrders: Number(summary.in_transit_orders ?? 0) || 0,
    pendingPayments: Number(summary.pending_payments ?? 0) || 0,
    completedOrders: Number(summary.completed_orders ?? 0) || 0,
  };
}

/**
 * Meaningful in-flight orders for the My Orders nav badge.
 * Combines paid/confirmed/processing + shipped (in transit) from one dashboard call.
 * Does not count cancelled, refunded, or completed.
 * Pending Payment / Arrived in TZ / Ready for Pickup are deferred until the
 * dashboard summary exposes them without list queries.
 */
export function resolveActiveOrdersBadgeCount(summary: CustomerDashboardSummary): number {
  return Math.max(0, summary.activeOrders + summary.inTransitOrders);
}

