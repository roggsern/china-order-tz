import { getApiUrl } from "@/lib/config/env";
import { ADMIN_AUTH_COOKIE, ADMIN_TOKEN_COOKIE } from "@/lib/admin/auth-cookie";
import { NextResponse } from "next/server";
import { cookies } from "next/headers";

/** POST /api/admin/logout → Laravel POST /api/v1/admin/logout (when token present) */
export async function POST() {
  const cookieStore = await cookies();
  const token = cookieStore.get(ADMIN_TOKEN_COOKIE)?.value;
  const apiUrl = getApiUrl();

  if (token && apiUrl) {
    try {
      await fetch(`${apiUrl}/api/v1/admin/logout`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        cache: "no-store",
      });
    } catch {
      // Still clear cookies locally.
    }
  }

  const response = NextResponse.json({
    success: true,
    message: "Logged out successfully",
  });

  const secure = process.env.NODE_ENV === "production";

  response.cookies.set(ADMIN_TOKEN_COOKIE, "", {
    httpOnly: true,
    sameSite: "lax",
    secure,
    path: "/",
    maxAge: 0,
  });
  response.cookies.set(ADMIN_AUTH_COOKIE, "", {
    httpOnly: false,
    sameSite: "lax",
    secure,
    path: "/",
    maxAge: 0,
  });

  return response;
}
