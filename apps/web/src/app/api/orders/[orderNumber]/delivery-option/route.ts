import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type RouteContext = {
  params: Promise<{ orderNumber: string }>;
};

async function proxyDeliveryOption(
  request: Request,
  orderNumber: string,
  method: string,
  body?: unknown,
) {
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

  const trimmed = orderNumber.trim();
  if (!trimmed) {
    return NextResponse.json(
      { success: false, message: "Order number is required." },
      { status: 422 },
    );
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: authorization,
  };

  let serialized: string | undefined;
  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
    serialized = JSON.stringify(body);
  }

  const upstream = await fetch(
    `${apiUrl}/api/v1/orders/${encodeURIComponent(trimmed)}/delivery-option`,
    {
      method,
      headers,
      body: serialized,
      cache: "no-store",
    },
  );

  const payload = await upstream.json().catch(() => null);
  return NextResponse.json(payload, { status: upstream.status });
}

/** GET /api/orders/[orderNumber]/delivery-option */
export async function GET(request: Request, context: RouteContext) {
  const { orderNumber } = await context.params;
  return proxyDeliveryOption(request, orderNumber, "GET");
}

/** POST /api/orders/[orderNumber]/delivery-option */
export async function POST(request: Request, context: RouteContext) {
  const { orderNumber } = await context.params;
  let body: unknown = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }
  return proxyDeliveryOption(request, orderNumber, "POST", body);
}

/** PATCH /api/orders/[orderNumber]/delivery-option */
export async function PATCH(request: Request, context: RouteContext) {
  const { orderNumber } = await context.params;
  let body: unknown = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }
  return proxyDeliveryOption(request, orderNumber, "PATCH", body);
}
