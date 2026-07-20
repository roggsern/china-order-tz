import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

function forwardSearchParams(request: Request, allowedKeys: string[]): string {
  const incoming = new URL(request.url).searchParams;
  const upstream = new URLSearchParams();

  for (const key of allowedKeys) {
    const value = incoming.get(key)?.trim();

    if (value) {
      upstream.set(key, value);
    }
  }

  return upstream.toString();
}

async function proxyCatalogGet(path: string, request: Request, allowedQueryKeys: string[]) {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const query = forwardSearchParams(request, allowedQueryKeys);
  const upstreamUrl = `${apiUrl}/api/v1${path}${query ? `?${query}` : ""}`;

  const upstream = await fetch(upstreamUrl, {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await upstream.json();

  return NextResponse.json(payload, { status: upstream.status });
}

export async function GET(request: Request) {
  return proxyCatalogGet("/products", request, [
    "page",
    "per_page",
    "featured",
    "category",
    "brand",
    "search",
  ]);
}
