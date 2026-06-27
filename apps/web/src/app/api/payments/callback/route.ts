import { NextResponse } from "next/server";
import { serverPaymentGateway } from "@/lib/payment/server/gateway";
import type { MpesaStkCallbackPayload } from "@/lib/payment/server/types";

export async function POST(request: Request) {
  try {
    const payload = (await request.json()) as MpesaStkCallbackPayload;
    const result = serverPaymentGateway.handlePaymentCallback(payload);

    return NextResponse.json(
      {
        ResultCode: result.handled ? 0 : 1,
        ResultDesc: result.message,
        transactionId: result.transactionId,
        status: result.status,
      },
      { status: 200 },
    );
  } catch (error) {
    const message = error instanceof Error ? error.message : "Callback processing failed.";
    return NextResponse.json({ ResultCode: 1, ResultDesc: message }, { status: 500 });
  }
}
