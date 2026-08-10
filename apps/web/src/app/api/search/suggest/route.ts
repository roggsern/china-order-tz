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

/** GET /api/search/suggest → Laravel GET /api/v1/search/suggest */
export async function GET(request: Request) {
  const { getServerApiUrl } = await import("@/lib/config/env");
  const apiUrl = getServerApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const query = forwardSearchParams(request, [
    "q",
    "scope",
    "limit_products",
    "limit_brands",
    "limit_stores",
    "limit_categories",
  ]);

  const upstream = await fetch(
    `${apiUrl}/api/v1/search/suggest${query ? `?${query}` : ""}`,
    {
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  const payload = await upstream.json().catch(() => ({
    success: false,
    message: "Unable to load search suggestions.",
  }));

  return NextResponse.json(payload, { status: upstream.status });
}
