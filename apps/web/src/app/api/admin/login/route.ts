import { getApiUrl } from "@/lib/config/env";
import {
  ADMIN_AUTH_COOKIE,
  ADMIN_TOKEN_COOKIE,
  adminTokenMaxAgeSeconds,
} from "@/lib/admin/auth-cookie";
import { NextResponse } from "next/server";

type LoginBody = {
  email?: string;
  password?: string;
};

/**
 * POST /api/admin/login → Laravel POST /api/v1/admin/login
 * Stores Sanctum token in an HttpOnly cookie for subsequent /api/admin/* proxies.
 * Does not set any client-writable auth gate cookie (RC1-G4A).
 */
export async function POST(request: Request) {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let body: LoginBody;

  try {
    body = (await request.json()) as LoginBody;
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid request body." },
      { status: 422 },
    );
  }

  const email = body.email?.trim().toLowerCase() ?? "";
  const password = body.password ?? "";

  if (!email || !password) {
    return NextResponse.json(
      { success: false, message: "Email and password are required." },
      { status: 422 },
    );
  }

  const upstream = await fetch(`${apiUrl}/api/v1/admin/login`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ email, password }),
    cache: "no-store",
  });

  const payload = await upstream.json().catch(() => null);

  if (!upstream.ok || !payload?.token) {
    return NextResponse.json(
      payload ?? { success: false, message: "Invalid credentials" },
      { status: upstream.status || 422 },
    );
  }

  const response = NextResponse.json(
    {
      success: true,
      message: payload.message ?? "Login successful",
      data: payload.data ?? null,
    },
    { status: 200 },
  );

  const secure = process.env.NODE_ENV === "production";
  const maxAge = adminTokenMaxAgeSeconds();

  response.cookies.set(ADMIN_TOKEN_COOKIE, payload.token, {
    httpOnly: true,
    sameSite: "lax",
    secure,
    path: "/",
    maxAge,
  });

  // Clear legacy forgeable gate cookie if present.
  response.cookies.set(ADMIN_AUTH_COOKIE, "", {
    httpOnly: false,
    sameSite: "lax",
    secure,
    path: "/",
    maxAge: 0,
  });

  return response;
}
