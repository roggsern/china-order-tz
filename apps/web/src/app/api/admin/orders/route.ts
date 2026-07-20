import { NextResponse } from "next/server";
import type { Order } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";
import { attachAdminOrderListSummary } from "@/lib/admin/order-list-summary";
import {
  extractAdminOrderFilterOptions,
  filterAdminOrders,
  parseAdminOrderQueryParams,
} from "@/lib/admin/order-query-filters";
import { listServerOrders, publishOrderUpsert } from "@/lib/admin/server/order-event-hub";
import { isAdminLocalOrderAuthorityEnabled } from "@/lib/config/env";
import {
  forwardAllowedSearchParams,
  proxyAdminApiRequest,
} from "@/lib/api/admin-upstream";
import { mapLaravelOrdersPayloadToAdminOrders } from "@/lib/admin/laravel-admin-orders";

/**
 * GET admin orders.
 * Production / default: Laravel OrderLifecycleEngine is authoritative (proxy).
 * Demo only when NEXT_PUBLIC_ADMIN_LOCAL_ORDER_AUTHORITY=true (non-production).
 */
export async function GET(request: Request) {
  if (!isAdminLocalOrderAuthorityEnabled()) {
    const searchParams = forwardAllowedSearchParams(request, ["page", "status", "per_page"]);
    const upstream = await proxyAdminApiRequest("/orders", { method: "GET", searchParams });
    if (!upstream.ok) {
      return upstream;
    }

    try {
      const payload = (await upstream.json()) as unknown;
      const mapped = mapLaravelOrdersPayloadToAdminOrders(payload);
      const url = new URL(request.url);
      const params = parseAdminOrderQueryParams(url);
      const withSummary = mapped.map((order) => attachAdminOrderListSummary(order));
      const orders = filterAdminOrders(withSummary, params);
      const filterOptions = extractAdminOrderFilterOptions(withSummary);

      return NextResponse.json({
        orders,
        total: orders.length,
        totalUnfiltered: withSummary.length,
        filterOptions,
        appliedFilters: params,
        authority: "laravel",
      });
    } catch {
      return NextResponse.json(
        { success: false, message: "Unable to map Laravel admin orders." },
        { status: 502 },
      );
    }
  }

  const url = new URL(request.url);
  const params = parseAdminOrderQueryParams(url);
  const allOrders = (await listServerOrders()).map((order) => attachAdminOrderListSummary(order));
  const orders = filterAdminOrders(allOrders, params);
  const filterOptions = extractAdminOrderFilterOptions(allOrders);

  return NextResponse.json({
    orders,
    total: orders.length,
    totalUnfiltered: allOrders.length,
    filterOptions,
    appliedFilters: params,
    authority: "local_demo",
  });
}

export async function POST(request: Request) {
  if (!isAdminLocalOrderAuthorityEnabled()) {
    return NextResponse.json(
      {
        success: false,
        message:
          "Local admin order status writes are disabled. Use Laravel admin APIs and specialist queues.",
      },
      { status: 403 },
    );
  }

  let body: { order?: Order };

  try {
    body = (await request.json()) as { order?: Order };
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  if (!body.order?.id || !body.order?.orderNumber) {
    return NextResponse.json({ error: "order with id and orderNumber is required." }, { status: 400 });
  }

  const order = normalizeOrder(body.order);
  const action = await publishOrderUpsert(order);

  return NextResponse.json({ ok: true, action, order });
}
