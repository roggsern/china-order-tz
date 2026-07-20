import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

export async function GET(request: Request) {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const { searchParams } = new URL(request.url);
  const qs = searchParams.toString();

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 10_000);

  try {
    const upstream = await fetch(
      `${apiUrl}/api/v1/storefront/homepage${qs ? `?${qs}` : ""}`,
      {
        method: "GET",
        headers: { Accept: "application/json" },
        cache: "no-store",
        signal: controller.signal,
      },
    );
    const payload = await upstream.json();
    return NextResponse.json(payload, { status: upstream.status });
  } catch (error) {
    const message =
      error instanceof Error && error.name === "AbortError"
        ? "Upstream homepage request timed out."
        : "Unable to reach homepage API.";
    return NextResponse.json({ success: false, message }, { status: 502 });
  } finally {
    clearTimeout(timer);
  }
}
