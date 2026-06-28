import { NextResponse } from "next/server";
import { publishDeliveryAssignment } from "@/lib/delivery/server/delivery-hub";

export async function POST(request: Request) {
  let body: { orderId?: string; driverName?: string };

  try {
    body = (await request.json()) as { orderId?: string; driverName?: string };
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  if (!body.orderId?.trim() || !body.driverName?.trim()) {
    return NextResponse.json({ error: "orderId and driverName are required." }, { status: 400 });
  }

  const result = await publishDeliveryAssignment(body.orderId, body.driverName);

  if (!result.delivery) {
    return NextResponse.json(
      { error: "Delivery record not found. Mark order as packed first." },
      { status: 404 },
    );
  }

  return NextResponse.json(result);
}
