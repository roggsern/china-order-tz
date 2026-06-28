import { NextResponse } from "next/server";
import { logServerPaymentEvent } from "@/lib/payment/server/payment-logger";
import { buildCallbackAck } from "@/lib/payments/mpesa";
import { paymentRouter } from "@/lib/payments/payment-router";
import type { MpesaCallbackResult } from "@/lib/payments/types";

export const dynamic = "force-dynamic";
export const runtime = "nodejs";

const MAX_CALLBACK_BYTES = 64 * 1024;

/** Safaricom requires HTTP 200 even when payload is invalid or processing fails. */
function safaricomResponse(
  result: MpesaCallbackResult,
  extra?: Record<string, unknown>,
): NextResponse {
  return NextResponse.json(
    {
      ...buildCallbackAck(result),
      duplicate: result.duplicate ?? false,
      transactionId: result.transactionId ?? null,
      orderId: result.orderId ?? null,
      status: result.status ?? null,
      paymentReference: result.paymentReference ?? null,
      ...extra,
    },
    { status: 200 },
  );
}

export async function GET() {
  return NextResponse.json({ error: "Method not allowed." }, { status: 405 });
}

/** M-Pesa-specific callback URL — delegates to PaymentRouter (same as unified callback). */
export async function POST(request: Request) {
  const contentLength = request.headers.get("content-length");
  if (contentLength && Number.parseInt(contentLength, 10) > MAX_CALLBACK_BYTES) {
    logServerPaymentEvent("mpesa:callback:payload_too_large", { contentLength });
    return safaricomResponse({
      handled: false,
      message: "Payload too large.",
    });
  }

  const contentType = request.headers.get("content-type") ?? "";
  if (!contentType.includes("application/json")) {
    logServerPaymentEvent("mpesa:callback:invalid_content_type", { contentType });
    return safaricomResponse({
      handled: false,
      message: "Expected application/json.",
    });
  }

  let rawBody: unknown;

  try {
    rawBody = await request.json();
  } catch {
    logServerPaymentEvent("mpesa:callback:invalid_json");
    return safaricomResponse({
      handled: false,
      message: "Invalid JSON body.",
    });
  }

  try {
    const result = await paymentRouter.handleCallback(rawBody);

    logServerPaymentEvent("mpesa:callback:processed", {
      handled: result.handled,
      duplicate: result.duplicate,
      orderId: result.orderId,
      transactionId: result.transactionId,
      status: result.status,
      message: result.message,
    });

    return safaricomResponse(result);
  } catch (error) {
    const message = error instanceof Error ? error.message : "Callback processing failed.";
    logServerPaymentEvent("mpesa:callback:error", { message });
    return safaricomResponse({
      handled: false,
      message,
    });
  }
}
