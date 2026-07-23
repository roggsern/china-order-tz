import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import {
  ADMIN_TOKEN_COOKIE,
  hasAdminSanctumSessionToken,
} from "@/lib/admin/auth-cookie";

function isPublicAdminPath(pathname: string): boolean {
  return pathname === "/admin/login" || pathname.startsWith("/admin/login/");
}

function isPublicAdminApiPath(pathname: string): boolean {
  return pathname === "/api/admin/login" || pathname.startsWith("/api/admin/login/");
}

/**
 * RC1-G4A — Gate admin UI/API on HttpOnly Sanctum token cookie only.
 * The legacy forgeable `china-order-tz-admin-auth` cookie is intentionally ignored.
 */
export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const hasSession = hasAdminSanctumSessionToken(
    request.cookies.get(ADMIN_TOKEN_COOKIE)?.value,
  );

  if (pathname.startsWith("/admin") && !isPublicAdminPath(pathname)) {
    if (!hasSession) {
      return NextResponse.redirect(new URL("/admin/login", request.url));
    }
  }

  if (pathname.startsWith("/api/admin") && !isPublicAdminApiPath(pathname)) {
    if (!hasSession) {
      return NextResponse.json({ error: "Unauthorized." }, { status: 401 });
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/admin/:path*", "/api/admin/:path*"],
};
