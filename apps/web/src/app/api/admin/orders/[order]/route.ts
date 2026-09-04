import { proxyAdminApiRequest } from "@/lib/api/admin-upstream";
import { isAdminLocalOrderAuthorityEnabled } from "@/lib/config/env";
import { mapLaravelAdminOrderPayloadToWebOrder } from "@/lib/admin/laravel-admin-orders";
import { attachAdminOrderListSummary } from "@/lib/admin/order-list-summary";
import { listServerOrders } from "@/lib/admin/server/order-event-hub";
import { NextResponse } from "next/server";

/** GET /api/admin/orders/[order] → Laravel GET /api/v1/admin/orders/{order} */
export async function GET(
  _request: Request,
  context: { params: Promise<{ order: string }> },
) {
  const { order } = await context.params;

  if (isAdminLocalOrderAuthorityEnabled()) {
    const local = (await listServerOrders()).find(
      (entry) => entry.id === order || entry.orderNumber === order,
    );
    if (!local) {
      return NextResponse.json(
        { success: false, message: "Order not found." },
        { status: 404 },
      );
    }

    const withSummary = attachAdminOrderListSummary(local);
    return NextResponse.json({
      success: true,
      data: withSummary,
      order: withSummary,
      authority: "local_demo",
    });
  }

  const upstream = await proxyAdminApiRequest(`/orders/${encodeURIComponent(order)}`, {
    method: "GET",
  });

  if (!upstream.ok) {
    return upstream;
  }

  try {
    const payload = (await upstream.json()) as unknown;
    const mapped = mapLaravelAdminOrderPayloadToWebOrder(payload);
    if (!mapped) {
      return NextResponse.json(
        { success: false, message: "Unable to map Laravel admin order." },
        { status: 502 },
      );
    }

    const withSummary = attachAdminOrderListSummary(mapped);
    return NextResponse.json({
      success: true,
      data: withSummary,
      order: withSummary,
      authority: "laravel",
    });
  } catch {
    return NextResponse.json(
      { success: false, message: "Unable to map Laravel admin order." },
      { status: 502 },
    );
  }
}
