"use client";

import { MpesaStkFlowSteps } from "@/components/payment/MpesaStkFlowSteps";
import type { StkFlowPhase, StkVisualStep } from "@/lib/payment/stk-flow";

interface PaymentStkStepIndicatorProps {
  activeStep: StkVisualStep;
}

function mapLegacyStep(step: StkVisualStep): StkFlowPhase {
  switch (step) {
    case "pending":
    case "initiating":
      return "initiating";
    case "processing":
    case "sending":
      return "sending";
    case "waiting_pin":
      return "waiting_pin";
    case "confirming":
      return "confirming";
    case "complete":
    case "success":
      return "success";
    case "failed":
      return "failed";
    default:
      return "initiating";
  }
}

/** @deprecated Use MpesaStkFlowSteps */
export function PaymentStkStepIndicator({ activeStep }: PaymentStkStepIndicatorProps) {
  return <MpesaStkFlowSteps phase={mapLegacyStep(activeStep)} />;
}
