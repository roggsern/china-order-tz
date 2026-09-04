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
import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";
import {
  mapLaravelOrdersPayloadToAdminOrders,
} from "@/lib/admin/laravel-admin-orders";
import {
  ADMIN_ORDERS_DEFAULT_PER_PAGE,
  clampAdminOrdersPerPage,
  extractLaravelPaginationMeta,
  mapSourceFilterToCommerceChannel,
  paginateLocalOrders,
} from "@/lib/admin/admin-orders-pagination";

function buildLaravelOrdersSearchParams(request: Request): URLSearchParams {
  const incoming = new URL(request.url).searchParams;
  const upstream = new URLSearchParams();

  const page = incoming.get("page")?.trim();
  if (page) {
    upstream.set("page", page);
  }

  const perPage = incoming.get("per_page")?.trim();
  if (perPage) {
    upstream.set("per_page", perPage);
  }

  const status = incoming.get("status")?.trim();
  if (status && status !== "all") {
    upstream.set("status", status);
  }

  const q = incoming.get("q")?.trim() || incoming.get("search")?.trim();
  if (q) {
    upstream.set("q", q);
  }

  const commerceChannel =
    incoming.get("commerce_channel")?.trim() ||
    mapSourceFilterToCommerceChannel(incoming.get("source")?.trim());
  if (commerceChannel) {
    upstream.set("commerce_channel", commerceChannel);
  }

  return upstream;
}

/**
 * GET admin orders.
 * Production / default: Laravel OrderLifecycleEngine is authoritative (proxy).
 * Demo only when NEXT_PUBLIC_ADMIN_LOCAL_ORDER_AUTHORITY=true (non-production).
 */
export async function GET(request: Request) {
  if (!isAdminLocalOrderAuthorityEnabled()) {
    const searchParams = buildLaravelOrdersSearchParams(request);
    const upstream = await proxyAdminApiRequest("/orders", { method: "GET", searchParams });
    if (!upstream.ok) {
      return upstream;
    }

    try {
      const payload = (await upstream.json()) as unknown;
      const mapped = mapLaravelOrdersPayloadToAdminOrders(payload);
      const url = new URL(request.url);
      const params = parseAdminOrderQueryParams(url);
      const requestedPerPage = Number.parseInt(url.searchParams.get("per_page") ?? "", 10);
      const requestedPage = Number.parseInt(url.searchParams.get("page") ?? "", 10);
      const withSummary = mapped.map((order) => attachAdminOrderListSummary(order));
      const filterOptions = extractAdminOrderFilterOptions(withSummary);
      const meta = extractLaravelPaginationMeta(payload, {
        page: Number.isFinite(requestedPage) && requestedPage > 0 ? requestedPage : 1,
        perPage: Number.isFinite(requestedPerPage)
          ? clampAdminOrdersPerPage(requestedPerPage)
          : ADMIN_ORDERS_DEFAULT_PER_PAGE,
        itemCount: withSummary.length,
      });

      return NextResponse.json({
        orders: withSummary,
        meta,
        total: meta.total,
        totalUnfiltered: meta.total,
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
  const requestedPerPage = Number.parseInt(url.searchParams.get("per_page") ?? "", 10);
  const requestedPage = Number.parseInt(url.searchParams.get("page") ?? "", 10);
  const allOrders = (await listServerOrders()).map((order) => attachAdminOrderListSummary(order));
  const filtered = filterAdminOrders(allOrders, params);
  const paged = paginateLocalOrders(
    filtered,
    Number.isFinite(requestedPage) && requestedPage > 0 ? requestedPage : 1,
    Number.isFinite(requestedPerPage) ? requestedPerPage : ADMIN_ORDERS_DEFAULT_PER_PAGE,
  );
  const filterOptions = extractAdminOrderFilterOptions(allOrders);

  return NextResponse.json({
    orders: paged.items,
    meta: paged.meta,
    total: paged.meta.total,
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
