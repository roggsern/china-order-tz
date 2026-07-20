import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

export async function GET() {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return NextResponse.json({ success: false, message: "API URL is not configured." }, { status: 500 });
  }

  const upstream = await fetch(`${apiUrl}/api/v1/storefront/tz/stores`, {
    method: "GET",
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await upstream.json();
  return NextResponse.json(payload, { status: upstream.status });
}
