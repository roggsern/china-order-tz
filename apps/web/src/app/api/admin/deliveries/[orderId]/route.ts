import { NextResponse } from "next/server";
import { getStoredDelivery } from "@/lib/delivery/server/delivery-store";

type RouteContext = {
  params: Promise<{ orderId: string }>;
};

export async function GET(_request: Request, context: RouteContext) {
  const { orderId } = await context.params;
  const delivery = await getStoredDelivery(orderId);

  if (!delivery) {
    return NextResponse.json({ error: "Delivery not found." }, { status: 404 });
  }

  return NextResponse.json({ delivery });
}
