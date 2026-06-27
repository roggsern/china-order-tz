import { NextResponse } from "next/server";
import { isPaymentTestMode } from "@/lib/payment/server/config";
import { serverPaymentGateway } from "@/lib/payment/server/gateway";
import type { InitiatePaymentInput } from "@/lib/payment/server/types";

export async function POST(request: Request) {
  if (!isPaymentTestMode()) {
    return NextResponse.json(
      { error: "Simulate payment is disabled in live mode." },
      { status: 403 },
    );
  }

  try {
    const body = (await request.json()) as Partial<InitiatePaymentInput>;

    if (!body.orderId || !body.orderNumber || !body.amount) {
      return NextResponse.json(
        { error: "orderId, orderNumber, and amount are required." },
        { status: 400 },
      );
    }

    const result = serverPaymentGateway.simulatePayment({
      orderId: body.orderId,
      orderNumber: body.orderNumber,
      amount: body.amount,
      phone: body.phone ?? "255700000000",
      accountReference: body.accountReference,
      description: body.description,
    });

    return NextResponse.json(result);
  } catch (error) {
    const message = error instanceof Error ? error.message : "Simulate payment failed.";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
