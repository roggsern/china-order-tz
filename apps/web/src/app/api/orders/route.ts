import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

export async function GET(request: Request) {
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

  const { searchParams } = new URL(request.url);
  const upstreamParams = new URLSearchParams();

  for (const key of ["page", "per_page", "filter"] as const) {
    const value = searchParams.get(key)?.trim();

    if (value) {
      upstreamParams.set(key, value);
    }
  }

  const query = upstreamParams.toString();
  const upstreamUrl = `${apiUrl}/api/v1/orders${query ? `?${query}` : ""}`;

  const upstream = await fetch(upstreamUrl, {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: authorization,
    },
    cache: "no-store",
  });

  const payload = await upstream.json();

  return NextResponse.json(payload, { status: upstream.status });
}
