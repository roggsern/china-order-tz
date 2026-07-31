import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

/**
 * POST /api/customer/logout → Laravel POST /api/v1/logout
 * Always returns a client-safe success so the storefront can clear local auth state
 * even when the token is already expired / missing.
 */
export async function POST(request: Request) {
  const authorization = request.headers.get("authorization");
  const apiUrl = getApiUrl();

  if (authorization && apiUrl) {
    try {
      await fetch(`${apiUrl}/api/v1/logout`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: authorization,
        },
        cache: "no-store",
      });
    } catch {
      // Local clear still proceeds on the client.
    }
  }

  return NextResponse.json({
    success: true,
    message: "Logged out successfully",
  });
}
