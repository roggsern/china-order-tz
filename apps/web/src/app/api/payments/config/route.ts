import { NextResponse } from "next/server";
import { getPaymentMode, isPaymentTestMode } from "@/lib/payments/config";
import { paymentRouter } from "@/lib/payments/payment-router";
import type { PaymentConfigResponse } from "@/lib/payments/types";

export async function GET() {
  const mode = getPaymentMode();
  const testMode = isPaymentTestMode();

  const config: PaymentConfigResponse = {
    testMode,
    mode,
    simulateEnabled: testMode,
    providers: paymentRouter.listProviders(),
  };

  return NextResponse.json(config);
}
