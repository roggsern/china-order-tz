import { NextResponse } from "next/server";
import { getPaymentMode, isPaymentTestMode } from "@/lib/payment/server/config";
import type { PaymentConfigResponse } from "@/lib/payment/server/types";

export async function GET() {
  const mode = getPaymentMode();
  const testMode = isPaymentTestMode();

  const config: PaymentConfigResponse = {
    testMode,
    mode,
    simulateEnabled: testMode,
  };

  return NextResponse.json(config);
}
