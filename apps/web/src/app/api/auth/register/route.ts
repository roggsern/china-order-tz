import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

const UPSTREAM_TIMEOUT_MS = 15_000;

type RegisterRequestBody = {
  name?: string;
  email?: string;
  phone?: string;
  password?: string;
  password_confirmation?: string;
  first_name?: string;
  last_name?: string;
};

function resolveCorrelationId(request: Request): string | null {
  return (
    request.headers.get("x-correlation-id")?.trim() ||
    request.headers.get("x-request-id")?.trim() ||
    null
  );
}

export async function POST(request: Request) {
  const apiUrl = getApiUrl();
  const correlationId = resolveCorrelationId(request);

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

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), UPSTREAM_TIMEOUT_MS);

  let upstream: Response;

  try {
    upstream = await fetch(`${apiUrl}/api/v1/register`, {
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
        first_name: body.first_name?.trim() || undefined,
        last_name: body.last_name?.trim() || undefined,
      }),
      cache: "no-store",
      signal: controller.signal,
    });
  } catch (error) {
    const aborted =
      (error instanceof Error && error.name === "AbortError") ||
      (typeof DOMException !== "undefined" && error instanceof DOMException && error.name === "AbortError");

    console.warn("auth.register.upstream_unreachable", {
      correlationId,
      aborted,
    });

    return NextResponse.json(
      {
        success: false,
        message: aborted
          ? "Registration service timed out. If you just created an account, try signing in."
          : "Unable to reach registration service. If you just created an account, try signing in.",
      },
      { status: 502 },
    );
  } finally {
    clearTimeout(timer);
  }

  const text = await upstream.text();

  try {
    return NextResponse.json(JSON.parse(text) as unknown, {
      status: upstream.status,
    });
  } catch {
    console.warn("auth.register.upstream_invalid_json", {
      correlationId,
      status: upstream.status,
    });

    return NextResponse.json(
      {
        success: false,
        message:
          "Registration service returned an unexpected response. If you just created an account, try signing in.",
      },
      { status: upstream.status >= 400 ? upstream.status : 502 },
    );
  }
}
