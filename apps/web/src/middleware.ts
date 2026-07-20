import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { ADMIN_AUTH_COOKIE } from "@/lib/admin/auth-cookie";

function isPublicAdminPath(pathname: string): boolean {
  return pathname === "/admin/login" || pathname.startsWith("/admin/login/");
}

function isPublicAdminApiPath(pathname: string): boolean {
  return pathname === "/api/admin/login" || pathname.startsWith("/api/admin/login/");
}

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (pathname.startsWith("/admin") && !isPublicAdminPath(pathname)) {
    if (!request.cookies.get(ADMIN_AUTH_COOKIE)?.value) {
      return NextResponse.redirect(new URL("/admin/login", request.url));
    }
  }

  if (pathname.startsWith("/api/admin") && !isPublicAdminApiPath(pathname)) {
    if (!request.cookies.get(ADMIN_AUTH_COOKIE)?.value) {
      return NextResponse.json({ error: "Unauthorized." }, { status: 401 });
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/admin/:path*", "/api/admin/:path*"],
};
