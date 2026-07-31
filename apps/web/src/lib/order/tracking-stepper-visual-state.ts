import type { CustomerProgressDisplayStep } from "@/lib/order/customer-progress";

/**
 * Presentation-only: when the active step is the final timeline stage,
 * render it with completed styling because the journey is finished.
 */
export function resolveTrackingStepVisualState(
  step: CustomerProgressDisplayStep,
  index: number,
  timelineLength: number,
): CustomerProgressDisplayStep["state"] {
  if (step.state === "current" && index === timelineLength - 1) {
    return "completed";
  }

  return step.state;
}
