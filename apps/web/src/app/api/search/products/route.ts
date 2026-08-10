import { NextResponse } from "next/server";

function forwardSearchParams(request: Request, allowedKeys: string[]): string {
  const incoming = new URL(request.url).searchParams;
  const upstream = new URLSearchParams();

  for (const key of allowedKeys) {
    const value = incoming.get(key);
    if (value === null) {
      continue;
    }
    const trimmed = value.trim();
    // Allow empty q so the API can return an empty result set.
    if (key === "q" || trimmed) {
      upstream.set(key, key === "q" ? value : trimmed);
    }
  }

  return upstream.toString();
}

/** GET /api/search/products → Laravel GET /api/v1/search/products */
export async function GET(request: Request) {
  const { getServerApiUrl } = await import("@/lib/config/env");
  const apiUrl = getServerApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const query = forwardSearchParams(request, ["q", "scope", "page", "per_page", "sort"]);

  const upstream = await fetch(
    `${apiUrl}/api/v1/search/products${query ? `?${query}` : ""}`,
    {
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  const payload = await upstream.json().catch(() => ({
    success: false,
    message: "Unable to load search results.",
  }));

  return NextResponse.json(payload, { status: upstream.status });
}
