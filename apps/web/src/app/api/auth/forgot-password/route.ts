import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type Body = {
  email?: string;
};

export async function POST(request: Request) {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let body: Body;
  try {
    body = (await request.json()) as Body;
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid request body." },
      { status: 400 },
    );
  }

  const email = body.email?.trim();
  if (!email) {
    return NextResponse.json(
      { success: false, message: "Email is required.", errors: { email: ["Email is required."] } },
      { status: 422 },
    );
  }

  const upstream = await fetch(`${apiUrl}/api/v1/auth/forgot-password`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ email }),
    cache: "no-store",
  });

  const text = await upstream.text();
  try {
    return NextResponse.json(JSON.parse(text), { status: upstream.status });
  } catch {
    return new Response(text, {
      status: upstream.status,
      headers: {
        "Content-Type": upstream.headers.get("content-type") ?? "text/plain",
      },
    });
  }
}
