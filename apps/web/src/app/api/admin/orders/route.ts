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

export async function GET(request: Request) {
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
  });
}

export async function POST(request: Request) {
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
