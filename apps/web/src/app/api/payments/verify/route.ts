import { NextResponse } from "next/server";
import { paymentService } from "@/lib/payments/paymentService";

export async function GET(request: Request) {
  const { searchParams } = new URL(request.url);
  const transactionId = searchParams.get("transactionId");

  if (!transactionId) {
    return NextResponse.json({ error: "transactionId query parameter is required." }, { status: 400 });
  }

  const result = paymentService.verifyPayment(transactionId);
  if (!result) {
    return NextResponse.json({ error: "Transaction not found." }, { status: 404 });
  }

  return NextResponse.json(result);
}
