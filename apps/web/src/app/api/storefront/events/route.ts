import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

export async function POST(request: Request) {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, message: "Invalid JSON body." }, { status: 422 });
  }

  const authorization = request.headers.get("authorization");

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 10_000);

  try {
    const upstream = await fetch(`${apiUrl}/api/v1/storefront/events`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(authorization ? { Authorization: authorization } : {}),
      },
      body: JSON.stringify(body),
      cache: "no-store",
      signal: controller.signal,
    });

    const payload = await upstream.json().catch(() => ({
      success: false,
      message: "Invalid upstream response.",
    }));

    return NextResponse.json(payload, { status: upstream.status });
  } catch (error) {
    const message =
      error instanceof Error && error.name === "AbortError"
        ? "Upstream storefront events request timed out."
        : "Unable to reach storefront events API.";

    return NextResponse.json({ success: false, message }, { status: 502 });
  } finally {
    clearTimeout(timer);
  }
}
