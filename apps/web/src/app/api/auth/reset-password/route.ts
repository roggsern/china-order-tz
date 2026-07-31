import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type Body = {
  email?: string;
  token?: string;
  password?: string;
  password_confirmation?: string;
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
  const token = body.token?.trim();
  const password = body.password;
  const passwordConfirmation = body.password_confirmation;

  if (!email || !token || !password || !passwordConfirmation) {
    return NextResponse.json(
      {
        success: false,
        message: "Email, token, password, and confirmation are required.",
      },
      { status: 422 },
    );
  }

  const upstream = await fetch(`${apiUrl}/api/v1/auth/reset-password`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      email,
      token,
      password,
      password_confirmation: passwordConfirmation,
    }),
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
