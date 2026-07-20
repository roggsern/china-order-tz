import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type RegisterRequestBody = {
  name?: string;
  email?: string;
  phone?: string;
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

  let body: RegisterRequestBody;

  try {
    body = (await request.json()) as RegisterRequestBody;
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid request body." },
      { status: 400 },
    );
  }

  const name = body.name?.trim();
  const email = body.email?.trim();
  const password = body.password;
  const passwordConfirmation = body.password_confirmation;

  if (!name || !email || !password || !passwordConfirmation) {
    return NextResponse.json(
      { success: false, message: "Name, email, and password are required." },
      { status: 422 },
    );
  }

  const upstream = await fetch(`${apiUrl}/api/v1/register`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      name,
      email,
      phone: body.phone?.trim() || undefined,
      password,
      password_confirmation: passwordConfirmation,
    }),
    cache: "no-store",
  });

  const payload = await upstream.json();

  return NextResponse.json(payload, { status: upstream.status });
}
