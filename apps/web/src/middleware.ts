import { NextResponse } from "next/server";

import type { NextRequest } from "next/server";

import {

  ADMIN_TOKEN_COOKIE,

  hasAdminSanctumSessionToken,

} from "@/lib/admin/auth-cookie";

import { parseStorefrontProductSlug } from "@/lib/api/catalog-proxy";



function isPublicAdminPath(pathname: string): boolean {

  return pathname === "/admin/login" || pathname.startsWith("/admin/login/");

}



function isPublicAdminApiPath(pathname: string): boolean {

  return pathname === "/api/admin/login" || pathname.startsWith("/api/admin/login/");

}



function rewriteStorefrontProductDetail(request: NextRequest): NextResponse | null {

  const productSlug = parseStorefrontProductSlug(request.nextUrl.pathname);



  if (!productSlug) {

    return null;

  }



  const url = request.nextUrl.clone();

  url.pathname = "/products/detail";

  url.searchParams.set("slug", productSlug);

  return NextResponse.rewrite(url);

}



/**

 * RC1-G4A — Gate admin UI/API on HttpOnly Sanctum token cookie only.

 * Storefront maintenance is enforced by Laravel APIs + (shop) layout redirect.

 *

 * Catalog/product detail rewrites use static routes so slug-based pages work when

 * Turbopack fails to compile App Router [slug] segments on Windows bind mounts.

 */

export function middleware(request: NextRequest) {

  const { pathname } = request.nextUrl;



  const productRewrite = rewriteStorefrontProductDetail(request);

  if (productRewrite) {

    return productRewrite;

  }



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

  matcher: [

    "/admin/:path*",

    "/api/admin/:path*",

    "/products/:path*",

  ],

};

