import { NextResponse } from "next/server";
import { DELIVERY_STATUS, type DeliveryStatus } from "@/lib/delivery/types";
import { publishDeliveryAdvance } from "@/lib/delivery/server/delivery-hub";
import { createDeliveryForOrder } from "@/lib/delivery/server/delivery-service";
import { getStoredOrder } from "@/lib/admin/server/order-store";

const VALID_STATUSES = new Set<string>(Object.values(DELIVERY_STATUS));

export async function POST(request: Request) {
  let body: { orderId?: string; status?: string; createIfMissing?: boolean };

  try {
    body = (await request.json()) as { orderId?: string; status?: string; createIfMissing?: boolean };
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  if (!body.orderId?.trim() || !body.status?.trim()) {
    return NextResponse.json({ error: "orderId and status are required." }, { status: 400 });
  }

  if (!VALID_STATUSES.has(body.status)) {
    return NextResponse.json({ error: "Invalid delivery status." }, { status: 400 });
  }

  const order = await getStoredOrder(body.orderId);
  if (!order) {
    return NextResponse.json({ error: "Order not found on server." }, { status: 404 });
  }

  if (body.createIfMissing && body.status === DELIVERY_STATUS.PACKED) {
    await createDeliveryForOrder(order, "admin");
  }

  const result = await publishDeliveryAdvance(
    body.orderId,
    body.status as DeliveryStatus,
    "admin",
  );

  if (!result.delivery) {
    return NextResponse.json(
      { error: "Delivery record not found. Mark order as packed first." },
      { status: 404 },
    );
  }

  return NextResponse.json(result);
}
