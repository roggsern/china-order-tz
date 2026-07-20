import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type ProxyOptions = {
  method?: string;
  body?: unknown;
};

export async function proxyCustomerApiRequest(
  request: Request,
  upstreamPath: string,
  options?: ProxyOptions,
): Promise<NextResponse> {
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

  const method = options?.method ?? request.method;
  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: authorization,
  };

  let body: string | undefined;

  if (options?.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.body);
  } else if (method !== "GET" && method !== "HEAD" && method !== "DELETE") {
    const text = await request.text();
    if (text) {
      headers["Content-Type"] = request.headers.get("content-type") ?? "application/json";
      body = text;
    }
  }

  const upstream = await fetch(`${apiUrl}/api/v1${upstreamPath}`, {
    method,
    headers,
    body,
    cache: "no-store",
  });

  const payload = await upstream.json();

  return NextResponse.json(payload, { status: upstream.status });
}
