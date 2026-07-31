import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type RouteContext = {
  params: Promise<{ slug: string }>;
};

/** GET /api/products/[slug]/reviews → approved product reviews (public) */
export async function GET(_request: Request, context: RouteContext) {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const { slug } = await context.params;
  const upstream = await fetch(
    `${apiUrl}/api/v1/products/${encodeURIComponent(slug)}/reviews`,
    {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  const payload = await upstream.json();
  return NextResponse.json(payload, { status: upstream.status });
}

/** POST /api/products/[slug]/reviews → submit review (authenticated) */
export async function POST(request: Request, context: RouteContext) {
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

  const { slug } = await context.params;
  const body = await request.text();

  const upstream = await fetch(
    `${apiUrl}/api/v1/products/${encodeURIComponent(slug)}/reviews`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        Authorization: authorization,
        "Content-Type": request.headers.get("content-type") ?? "application/json",
      },
      body: body || undefined,
      cache: "no-store",
    },
  );

  const payload = await upstream.json();
  return NextResponse.json(payload, { status: upstream.status });
}
