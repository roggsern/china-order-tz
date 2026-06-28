import { NextResponse } from "next/server";
import {
  mapPaymentMethodToProvider,
  paymentRouter,
} from "@/lib/payments/payment-router";
import { PAYMENT_PROVIDER } from "@/lib/payments/providers/types";
import type { InitiateStkPushInput } from "@/lib/payments/types";
import type { PaymentMethodCode } from "@/lib/types/payment";

export async function POST(request: Request) {
  try {
    const body = (await request.json()) as Partial<InitiateStkPushInput>;

    if (!body.orderId || !body.orderNumber || !body.amount || !body.phone) {
      return NextResponse.json(
        { error: "orderId, orderNumber, amount, and phone are required." },
        { status: 400 },
      );
    }

    if (body.amount <= 0) {
      return NextResponse.json({ error: "Amount must be greater than zero." }, { status: 400 });
    }

    const providerCode =
      body.provider ??
      (body.paymentMethod
        ? mapPaymentMethodToProvider(body.paymentMethod as PaymentMethodCode)
        : null) ??
      PAYMENT_PROVIDER.MPESA;

    const result = await paymentRouter.initiatePayment(providerCode, {
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
