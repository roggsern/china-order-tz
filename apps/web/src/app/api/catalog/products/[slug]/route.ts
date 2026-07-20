import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type RouteContext = {
  params: Promise<{ slug: string }>;
};

export async function GET(_request: Request, context: RouteContext) {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const { slug } = await context.params;
  const upstream = await fetch(`${apiUrl}/api/v1/products/${encodeURIComponent(slug)}`, {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await upstream.json();

  return NextResponse.json(payload, { status: upstream.status });
}
