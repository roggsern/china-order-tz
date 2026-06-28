import { NextResponse } from "next/server";
import { buildCallbackAck } from "@/lib/payments/mpesa";
import { paymentRouter } from "@/lib/payments/payment-router";
import { logServerPaymentEvent } from "@/lib/payment/server/payment-logger";

export const dynamic = "force-dynamic";
export const runtime = "nodejs";

const MAX_CALLBACK_BYTES = 64 * 1024;

function callbackResponse(
  result: Awaited<ReturnType<typeof paymentRouter.handleCallback>>,
): NextResponse {
  const isMpesaShape =
    !result.provider || result.provider === "mpesa";

  if (isMpesaShape) {
    return NextResponse.json(
      {
        ...buildCallbackAck(result),
        duplicate: result.duplicate ?? false,
        transactionId: result.transactionId ?? null,
        orderId: result.orderId ?? null,
        status: result.status ?? null,
        provider: result.provider ?? null,
      },
      { status: 200 },
    );
  }

  return NextResponse.json(
    {
      success: result.handled,
      message: result.message,
      duplicate: result.duplicate ?? false,
      transactionId: result.transactionId ?? null,
      orderId: result.orderId ?? null,
      status: result.status ?? null,
      provider: result.provider ?? null,
    },
    { status: 200 },
  );
}

export async function GET() {
  return NextResponse.json({ error: "Method not allowed." }, { status: 405 });
}

/** Unified payment callback — routes to the correct provider by payload shape. */
export async function POST(request: Request) {
  const contentLength = request.headers.get("content-length");
  if (contentLength && Number.parseInt(contentLength, 10) > MAX_CALLBACK_BYTES) {
    logServerPaymentEvent("callback:payload_too_large", { contentLength });
    return NextResponse.json(
      { success: false, message: "Payload too large." },
      { status: 200 },
    );
  }

  let rawBody: unknown;

  try {
    rawBody = await request.json();
  } catch {
    logServerPaymentEvent("callback:invalid_json");
    return NextResponse.json(
      { success: false, message: "Invalid JSON body." },
      { status: 200 },
    );
  }

  try {
    const result = await paymentRouter.handleCallback(rawBody);

    logServerPaymentEvent("callback:processed", {
      handled: result.handled,
      provider: result.provider,
      duplicate: result.duplicate,
      orderId: result.orderId,
      transactionId: result.transactionId,
      status: result.status,
    });

    return callbackResponse(result);
  } catch (error) {
    const message = error instanceof Error ? error.message : "Callback processing failed.";
    logServerPaymentEvent("callback:error", { message });
    return NextResponse.json({ success: false, message }, { status: 200 });
  }
}
