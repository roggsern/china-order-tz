import { getApiUrl } from "@/lib/config/env";
import { ADMIN_TOKEN_COOKIE } from "@/lib/admin/auth-cookie";
import { cookies } from "next/headers";
import { NextResponse } from "next/server";

type ProxyOptions = {
  method?: string;
  body?: unknown;
  searchParams?: URLSearchParams | string;
  /** When true, stream upstream body/content-type (HTML/PDF) instead of JSON-only. */
  raw?: boolean;
  accept?: string;
};

async function getSessionAdminToken(): Promise<string | null> {
  try {
    const cookieStore = await cookies();
    const value = cookieStore.get(ADMIN_TOKEN_COOKIE)?.value?.trim();
    return value || null;
  } catch {
    return null;
  }
}

/**
 * Require the logged-in admin's HttpOnly Sanctum cookie.
 * Never authenticates via ADMIN_API_EMAIL / ADMIN_API_PASSWORD (RC1-G4A).
 */
async function requireSessionAdminToken(): Promise<string> {
  const sessionToken = await getSessionAdminToken();
  if (!sessionToken) {
    throw new Error("Unauthenticated.");
  }

  return sessionToken;
}

function unauthenticatedResponse(): NextResponse {
  return NextResponse.json(
    { success: false, message: "Unauthenticated." },
    { status: 401 },
  );
}

/**
 * Proxies an authenticated admin request to Laravel `/api/v1/admin/...`.
 * Requires HttpOnly Sanctum session cookie set by POST /api/admin/login.
 */
export async function proxyAdminApiRequest(
  upstreamPath: string,
  options?: ProxyOptions,
): Promise<NextResponse> {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let token: string;
  try {
    token = await requireSessionAdminToken();
  } catch {
    return unauthenticatedResponse();
  }

  const method = options?.method ?? "GET";
  const headers: Record<string, string> = {
    Accept: options?.accept ?? (options?.raw ? "*/*" : "application/json"),
    Authorization: `Bearer ${token}`,
  };

  let body: string | undefined;

  if (options?.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.body);
  }

  const query =
    typeof options?.searchParams === "string"
      ? options.searchParams
      : options?.searchParams?.toString() || "";

  const upstreamUrl = `${apiUrl}/api/v1/admin${upstreamPath}${query ? `?${query}` : ""}`;

  const upstream = await fetch(upstreamUrl, {
    method,
    headers,
    body,
    cache: "no-store",
  });

  if (options?.raw) {
    const buffer = await upstream.arrayBuffer();
    const responseHeaders = new Headers();
    const contentType = upstream.headers.get("content-type");
    const disposition = upstream.headers.get("content-disposition");
    if (contentType) responseHeaders.set("Content-Type", contentType);
    if (disposition) responseHeaders.set("Content-Disposition", disposition);
    return new NextResponse(buffer, { status: upstream.status, headers: responseHeaders });
  }

  const raw = await upstream.text();
  let payload: unknown = null;

  try {
    payload = raw ? JSON.parse(raw) : null;
  } catch {
    return NextResponse.json(
      {
        success: false,
        message: "Unexpected response from the admin API.",
      },
      { status: 502 },
    );
  }

  return NextResponse.json(payload, { status: upstream.status });
}

/**
 * Proxies multipart/form-data (e.g. product image upload) without forcing JSON Content-Type.
 */
export async function proxyAdminMultipartRequest(
  upstreamPath: string,
  formData: FormData,
  method: string = "POST",
): Promise<NextResponse> {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let token: string;
  try {
    token = await requireSessionAdminToken();
  } catch {
    return unauthenticatedResponse();
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: `Bearer ${token}`,
  };

  const upstreamUrl = `${apiUrl}/api/v1/admin${upstreamPath}`;

  const upstream = await fetch(upstreamUrl, {
    method,
    headers,
    body: formData,
    cache: "no-store",
  });

  const raw = await upstream.text();
  let payload: unknown = null;

  try {
    payload = raw ? JSON.parse(raw) : null;
  } catch {
    return NextResponse.json(
      {
        success: false,
        message: "Unexpected response from the admin API.",
      },
      { status: 502 },
    );
  }

  return NextResponse.json(payload, { status: upstream.status });
}

export function forwardAllowedSearchParams(
  request: Request,
  allowedKeys: string[],
): URLSearchParams {
  const incoming = new URL(request.url).searchParams;
  const upstream = new URLSearchParams();

  for (const key of allowedKeys) {
    const value = incoming.get(key)?.trim();
    if (value) {
      upstream.set(key, value);
    }
  }

  return upstream;
}

/**
 * Proxies a binary/file admin response (CSV, XLSX, etc.) without JSON-parsing the body.
 */
export async function proxyAdminBinaryRequest(
  upstreamPath: string,
  options?: ProxyOptions,
): Promise<NextResponse> {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let token: string;
  try {
    token = await requireSessionAdminToken();
  } catch {
    return unauthenticatedResponse();
  }

  const method = options?.method ?? "GET";
  const headers: Record<string, string> = {
    Accept: "*/*",
    Authorization: `Bearer ${token}`,
  };

  let body: string | undefined;

  if (options?.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.body);
  }

  const query =
    typeof options?.searchParams === "string"
      ? options.searchParams
      : options?.searchParams?.toString() || "";

  const upstreamUrl = `${apiUrl}/api/v1/admin${upstreamPath}${query ? `?${query}` : ""}`;

  const upstream = await fetch(upstreamUrl, {
    method,
    headers,
    body,
    cache: "no-store",
  });

  const contentType = upstream.headers.get("content-type") || "";

  if (contentType.includes("application/json")) {
    const raw = await upstream.text();
    let payload: unknown = null;
    try {
      payload = raw ? JSON.parse(raw) : null;
    } catch {
      return NextResponse.json(
        { success: false, message: "Unexpected response from the admin API." },
        { status: 502 },
      );
    }
    return NextResponse.json(payload, { status: upstream.status });
  }

  const buffer = await upstream.arrayBuffer();
  const responseHeaders = new Headers();
  if (contentType) {
    responseHeaders.set("Content-Type", contentType);
  }
  const disposition = upstream.headers.get("content-disposition");
  if (disposition) {
    responseHeaders.set("Content-Disposition", disposition);
  }
  responseHeaders.set("Cache-Control", "no-store");

  return new NextResponse(buffer, {
    status: upstream.status,
    headers: responseHeaders,
  });
}
