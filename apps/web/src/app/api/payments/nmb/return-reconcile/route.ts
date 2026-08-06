import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

/**
 * Public BFF proxy for NMB browser-return reconciliation.
 * Does not require a customer session — proof travels in the JSON body.
 */
export async function POST(request: Request) {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let body: string;
  try {
    body = await request.text();
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid request body." },
      { status: 400 },
    );
  }

  if (!body.trim()) {
    return NextResponse.json(
      { success: false, message: "Request body is required." },
      { status: 422 },
    );
  }

  const upstream = await fetch(`${apiUrl}/api/v1/payments/nmb/return-reconcile`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": request.headers.get("content-type") ?? "application/json",
    },
    body,
    cache: "no-store",
  });

  const payload = await upstream.json();

  return NextResponse.json(payload, { status: upstream.status });
}
