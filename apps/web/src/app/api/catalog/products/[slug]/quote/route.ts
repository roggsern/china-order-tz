import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type RouteContext = {
  params: Promise<{ slug: string }>;
};

/** POST /api/catalog/products/[slug]/quote → Laravel ResolvePrice quote */
export async function POST(request: Request, context: RouteContext) {
  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const { slug } = await context.params;

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid JSON body." },
      { status: 422 },
    );
  }

  const upstream = await fetch(
    `${apiUrl}/api/v1/products/${encodeURIComponent(slug)}/quote`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(body),
      cache: "no-store",
    },
  );

  const payload = await upstream.json();
  return NextResponse.json(payload, { status: upstream.status });
}
