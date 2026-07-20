import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type LoginRequestBody = {
  email?: string;
  password?: string;
};

export async function POST(request: Request) {
  const apiUrl = getApiUrl();
  
  console.log("API_URL_USED:", apiUrl);

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  let body: LoginRequestBody;

  try {
    body = (await request.json()) as LoginRequestBody;
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid request body." },
      { status: 400 },
    );
  }

  const email = body.email?.trim();
  const password = body.password;

  if (!email || !password) {
    return NextResponse.json(
      { success: false, message: "Email and password are required." },
      { status: 422 },
    );
  }

  const upstream = await fetch(`${apiUrl}/api/v1/login`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ email, password }),
    cache: "no-store",
  });

  const text = await upstream.text();

  console.log("STATUS:", upstream.status);
  console.log("BODY:", text);

  try {
    return NextResponse.json(JSON.parse(text), {
      status: upstream.status,
    });
  } catch {
    return new Response(text, {
      status: upstream.status,
      headers: {
        "Content-Type":
          upstream.headers.get("content-type") ?? "text/plain",
      },
    });
  }
}