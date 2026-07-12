import { getApiUrl } from "@/lib/config/env";
import { NextResponse } from "next/server";

type InitiateRequestBody = {
  paymentId?: string;
};

export async function POST(request: Request) {
  const authorization = request.headers.get("authorization");

  if (!authorization) {
    return NextResponse.json(
      { success: false, message: "Authentication is required." },
      { status: 401 },
    );
  }

  let body: InitiateRequestBody;

  try {
    body = (await request.json()) as InitiateRequestBody;
  } catch {
    return NextResponse.json(
      { success: false, message: "Invalid request body." },
      { status: 400 },
    );
  }

  const paymentId = body.paymentId?.trim();

  if (!paymentId) {
    return NextResponse.json(
      { success: false, message: "paymentId is required." },
      { status: 422 },
    );
  }

  const apiUrl = getApiUrl();

  if (!apiUrl) {
    return NextResponse.json(
      { success: false, message: "API URL is not configured." },
      { status: 500 },
    );
  }

  const upstream = await fetch(`${apiUrl}/api/v1/payments/${paymentId}/initiate`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: authorization,
    },
  });

  const payload = await upstream.json();

  return NextResponse.json(payload, { status: upstream.status });
}
