import { NextResponse } from "next/server";
import { serverPaymentGateway } from "@/lib/payment/server/gateway";
import type { InitiatePaymentInput } from "@/lib/payment/server/types";

export async function POST(request: Request) {
  try {
    const body = (await request.json()) as Partial<InitiatePaymentInput>;

    if (!body.orderId || !body.orderNumber || !body.amount || !body.phone) {
      return NextResponse.json(
        { error: "orderId, orderNumber, amount, and phone are required." },
        { status: 400 },
      );
    }

    if (body.amount <= 0) {
      return NextResponse.json({ error: "Amount must be greater than zero." }, { status: 400 });
    }

    const result = await serverPaymentGateway.initiatePayment({
      orderId: body.orderId,
      orderNumber: body.orderNumber,
      amount: body.amount,
      phone: body.phone,
      accountReference: body.accountReference,
      description: body.description,
    });

    return NextResponse.json(result, { status: result.success ? 200 : 422 });
  } catch (error) {
    const message = error instanceof Error ? error.message : "Payment initiation failed.";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
