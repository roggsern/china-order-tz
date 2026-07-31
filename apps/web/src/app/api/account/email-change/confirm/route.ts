import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

/**
 * Confirm may be called with or without a bearer token (signed email link).
 * Prefer proxying Authorization when present.
 */
export async function POST(request: Request) {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let body: { token?: string };
  try {
    body = (await request.json()) as { token?: string };
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid request body." },
      { status: 400 },
    );
  }

  const token = body.token?.trim();
  if (!token) {
    return NextResponse.json(
      { success: false, message: "Confirmation token is required." },
      { status: 422 },
    );
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };
  const authorization = request.headers.get("authorization");
  if (authorization) {
    headers.Authorization = authorization;
  }

  const upstream = await fetch(`${apiUrl}/api/v1/account/email-change/confirm`, {
    method: "POST",
    headers,
    body: JSON.stringify({ token }),
    cache: "no-store",
  });

  const payload = await upstream.json();
  return NextResponse.json(payload, { status: upstream.status });
}
