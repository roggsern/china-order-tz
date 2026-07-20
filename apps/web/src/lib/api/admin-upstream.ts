import { getApiUrl, isDevelopment } from "@/lib/config/env";
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

type CachedAdminToken = {
  token: string;
  expiresAt: number;
};

let cachedServiceToken: CachedAdminToken | null = null;

function getAdminApiCredentials(): { email: string; password: string } | null {
  const email =
    process.env.ADMIN_API_EMAIL?.trim() ||
    (isDevelopment() ? "admin@chinaordertz.com" : "");
  const password =
    process.env.ADMIN_API_PASSWORD?.trim() || (isDevelopment() ? "password" : "");

  if (!email || !password) {
    return null;
  }

  return { email, password };
}

async function loginAdminForUpstream(apiUrl: string): Promise<string> {
  const credentials = getAdminApiCredentials();

  if (!credentials) {
    throw new Error(
      "Admin API credentials are not configured. Set ADMIN_API_EMAIL and ADMIN_API_PASSWORD.",
    );
  }

  const response = await fetch(`${apiUrl}/api/v1/admin/login`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(credentials),
    cache: "no-store",
  });

  const payload = (await response.json().catch(() => null)) as {
    success?: boolean;
    token?: string;
    message?: string;
  } | null;

  if (!response.ok || !payload?.token) {
    throw new Error(payload?.message?.trim() || "Unable to authenticate with the admin API.");
  }

  return payload.token;
}

async function getSessionAdminToken(): Promise<string | null> {
  try {
    const cookieStore = await cookies();
    return cookieStore.get(ADMIN_TOKEN_COOKIE)?.value ?? null;
  } catch {
    return null;
  }
}

/** Prefer the logged-in admin's Sanctum cookie; fall back to ADMIN_API_* service login. */
export async function getAdminUpstreamToken(apiUrl: string): Promise<string> {
  const sessionToken = await getSessionAdminToken();
  if (sessionToken) {
    return sessionToken;
  }

  const now = Date.now();

  if (cachedServiceToken && cachedServiceToken.expiresAt > now) {
    return cachedServiceToken.token;
  }

  const token = await loginAdminForUpstream(apiUrl);
  cachedServiceToken = { token, expiresAt: now + 50 * 60 * 1000 };
  return token;
}

export function clearAdminUpstreamTokenCache(): void {
  cachedServiceToken = null;
}

/**
 * Proxies an authenticated admin request to Laravel `/api/v1/admin/...`.
 * Relies on Next middleware cookie gate for `/api/admin/*` (except login).
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
  let usedSessionToken = false;

  try {
    const sessionToken = await getSessionAdminToken();
    usedSessionToken = Boolean(sessionToken);
    token = sessionToken ?? (await getAdminUpstreamToken(apiUrl));
  } catch (error) {
    return NextResponse.json(
      {
        success: false,
        message: error instanceof Error ? error.message : "Admin API authentication failed.",
      },
      { status: 502 },
    );
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

  let upstream = await fetch(upstreamUrl, {
    method,
    headers,
    body,
    cache: "no-store",
  });

  if (upstream.status === 401 && !usedSessionToken) {
    clearAdminUpstreamTokenCache();
    try {
      token = await loginAdminForUpstream(apiUrl);
      cachedServiceToken = { token, expiresAt: Date.now() + 50 * 60 * 1000 };
      headers.Authorization = `Bearer ${token}`;
      upstream = await fetch(upstreamUrl, {
        method,
        headers,
        body,
        cache: "no-store",
      });
    } catch (error) {
      return NextResponse.json(
        {
          success: false,
          message: error instanceof Error ? error.message : "Admin API authentication failed.",
        },
        { status: 502 },
      );
    }
  }

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
  let usedSessionToken = false;

  try {
    const sessionToken = await getSessionAdminToken();
    usedSessionToken = Boolean(sessionToken);
    token = sessionToken ?? (await getAdminUpstreamToken(apiUrl));
  } catch (error) {
    return NextResponse.json(
      {
        success: false,
        message: error instanceof Error ? error.message : "Admin API authentication failed.",
      },
      { status: 502 },
    );
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: `Bearer ${token}`,
  };

  const upstreamUrl = `${apiUrl}/api/v1/admin${upstreamPath}`;

  let upstream = await fetch(upstreamUrl, {
    method,
    headers,
    body: formData,
    cache: "no-store",
  });

  if (upstream.status === 401 && !usedSessionToken) {
    clearAdminUpstreamTokenCache();
    try {
      token = await loginAdminForUpstream(apiUrl);
      cachedServiceToken = { token, expiresAt: Date.now() + 50 * 60 * 1000 };
      headers.Authorization = `Bearer ${token}`;
      upstream = await fetch(upstreamUrl, {
        method,
        headers,
        body: formData,
        cache: "no-store",
      });
    } catch (error) {
      return NextResponse.json(
        {
          success: false,
          message: error instanceof Error ? error.message : "Admin API authentication failed.",
        },
        { status: 502 },
      );
    }
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
 * Forwards Authorization and preserves Content-Type / Content-Disposition when present.
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
  let usedSessionToken = false;

  try {
    const sessionToken = await getSessionAdminToken();
    usedSessionToken = Boolean(sessionToken);
    token = sessionToken ?? (await getAdminUpstreamToken(apiUrl));
  } catch (error) {
    return NextResponse.json(
      {
        success: false,
        message: error instanceof Error ? error.message : "Admin API authentication failed.",
      },
      { status: 502 },
    );
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

  let upstream = await fetch(upstreamUrl, {
    method,
    headers,
    body,
    cache: "no-store",
  });

  if (upstream.status === 401 && !usedSessionToken) {
    clearAdminUpstreamTokenCache();
    try {
      token = await loginAdminForUpstream(apiUrl);
      cachedServiceToken = { token, expiresAt: Date.now() + 50 * 60 * 1000 };
      headers.Authorization = `Bearer ${token}`;
      upstream = await fetch(upstreamUrl, {
        method,
        headers,
        body,
        cache: "no-store",
      });
    } catch (error) {
      return NextResponse.json(
        {
          success: false,
          message: error instanceof Error ? error.message : "Admin API authentication failed.",
        },
        { status: 502 },
      );
    }
  }

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
