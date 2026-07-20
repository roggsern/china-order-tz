import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type RouteContext = {
  params: Promise<{ slug: string }>;
};

/** GET /api/catalog/products/[slug]/configuration → Laravel configuration schema */
export async function GET(request: Request, context: RouteContext) {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const { slug } = await context.params;
  const inbound = new URL(request.url);
  const upstreamUrl = new URL(
    `${apiUrl}/api/v1/products/${encodeURIComponent(slug)}/configuration`,
  );
  inbound.searchParams.forEach((value, key) => {
    upstreamUrl.searchParams.append(key, value);
  });

  const upstream = await fetch(upstreamUrl.toString(), {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await upstream.json();
  return NextResponse.json(payload, { status: upstream.status });
}
