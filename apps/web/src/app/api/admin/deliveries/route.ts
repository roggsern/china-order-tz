import { NextResponse } from "next/server";
import { listActiveDeliveries, listStoredDeliveries } from "@/lib/delivery/server/delivery-store";

export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const activeOnly = searchParams.get("active") === "1";

  const deliveries = activeOnly ? await listActiveDeliveries() : await listStoredDeliveries();
  return NextResponse.json({ deliveries });
}
