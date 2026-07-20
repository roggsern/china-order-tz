import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type RouteContext = {
  params: Promise<{ orderNumber: string }>;
};

/** GET /api/orders/[orderNumber]/tracking → Laravel GET /api/v1/orders/{order}/tracking */
export async function GET(request: Request, context: RouteContext) {
  const authorization = request.headers.get("authorization");
  if (!authorization) {
    return NextResponse.json(
      { success: false, message: "Authentication is required." },
      { status: 401 },
    );
  }

  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const { orderNumber } = await context.params;
  const trimmed = orderNumber.trim();
  if (!trimmed) {
    return NextResponse.json(
      { success: false, message: "Order number is required." },
      { status: 422 },
    );
  }

  const upstream = await fetch(
    `${apiUrl}/api/v1/orders/${encodeURIComponent(trimmed)}/tracking`,
    {
      method: "GET",
      headers: {
        Accept: "application/json",
        Authorization: authorization,
      },
      cache: "no-store",
    },
  );

  const payload = await upstream.json().catch(() => null);
  return NextResponse.json(payload, { status: upstream.status });
}
