import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type RouteContext = {
  params: Promise<{ id: string; hash: string }>;
};

/** GET /api/account/email/verify/:id/:hash?expires=&signature= → Laravel signed verify */
export async function GET(request: Request, context: RouteContext) {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const { id, hash } = await context.params;
  const incoming = new URL(request.url);
  const qs = incoming.searchParams.toString();

  const upstream = await fetch(
    `${apiUrl}/api/v1/account/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}${qs ? `?${qs}` : ""}`,
    {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  const payload = await upstream.json();
  return NextResponse.json(payload, { status: upstream.status });
}
