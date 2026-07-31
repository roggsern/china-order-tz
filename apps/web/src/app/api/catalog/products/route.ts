import { NextResponse } from "next/server";
import {
  proxyCatalogProductShow,
  resolveCatalogProductSlugFromRequest,
} from "@/lib/api/catalog-proxy";

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

async function proxyCatalogListGet(request: Request) {
  const { getServerApiUrl } = await import("@/lib/config/env");
  const apiUrl = getServerApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const query = forwardSearchParams(request, [
    "page",
    "per_page",
    "featured",
    "category",
    "brand",
    "search",
    "store",
    "origin",
    "commerce_channel",
  ]);
  const upstreamUrl = `${apiUrl}/api/v1/products${query ? `?${query}` : ""}`;

  const upstream = await fetch(upstreamUrl, {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = await upstream.json();

  return NextResponse.json(payload, { status: upstream.status });
}

/**
 * GET /api/catalog/products — list catalog products
 * GET /api/catalog/products?slug={slug} — show single product (static BFF fallback)
 */
export async function GET(request: Request) {
  const slug = resolveCatalogProductSlugFromRequest(request);

  if (slug) {
    return proxyCatalogProductShow(slug);
  }

  return proxyCatalogListGet(request);
}
