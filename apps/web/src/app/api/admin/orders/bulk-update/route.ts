import { NextResponse } from "next/server";
import { isBulkOrderStatus } from "@/lib/admin/bulk-order-status";
import { publishBulkOrderStatusUpdate } from "@/lib/admin/server/order-event-hub";
import { isAdminLocalOrderAuthorityEnabled } from "@/lib/config/env";

export async function POST(request: Request) {
  if (!isAdminLocalOrderAuthorityEnabled()) {
    return NextResponse.json(
      {
        success: false,
        message:
          "Bulk local status updates are disabled. Advance orders via Laravel fulfillment, warehouse, or shipment engines.",
      },
      { status: 403 },
    );
  }

  let body: { orderIds?: unknown; status?: unknown };

  try {
    body = (await request.json()) as { orderIds?: unknown; status?: unknown };
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  if (!Array.isArray(body.orderIds) || body.orderIds.length === 0) {
    return NextResponse.json({ error: "orderIds must be a non-empty array." }, { status: 400 });
  }

  const orderIds = body.orderIds.filter((id): id is string => typeof id === "string" && id.trim().length > 0);
  if (orderIds.length === 0) {
    return NextResponse.json({ error: "orderIds must contain valid order IDs." }, { status: 400 });
  }

  if (!isBulkOrderStatus(body.status)) {
    return NextResponse.json(
      { error: 'status must be "processing", "shipped", or "delivered".' },
      { status: 400 },
    );
  }

  const { updated, skipped } = await publishBulkOrderStatusUpdate({
    orderIds,
    status: body.status,
  });

  return NextResponse.json({
    ok: true,
    status: body.status,
    updatedCount: updated.length,
    skippedCount: skipped.length,
    orderIds: updated.map((order) => order.id),
    orders: updated,
    skipped,
  });
}
