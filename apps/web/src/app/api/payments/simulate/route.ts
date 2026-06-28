import { NextResponse } from "next/server";
import { isProduction } from "@/lib/config/env";
import { isPaymentTestMode } from "@/lib/payments/config";
import { paymentService } from "@/lib/payments/paymentService";
import type { InitiateStkPushInput } from "@/lib/payments/types";

export async function POST(request: Request) {
  if (isProduction() || !isPaymentTestMode()) {
    return NextResponse.json(
      { error: "Simulate payment is disabled in production." },
      { status: 404 },
    );
  }

  try {
    const body = (await request.json()) as Partial<InitiateStkPushInput>;

    if (!body.orderId || !body.orderNumber || !body.amount) {
      return NextResponse.json(
        { error: "orderId, orderNumber, and amount are required." },
        { status: 400 },
      );
    }

    const result = paymentService.simulateSTKPush({
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
