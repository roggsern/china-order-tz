"use client";

import { motion } from "framer-motion";
import type { StkVisualStep } from "@/lib/payment/stk-flow";

const STEPS: { id: StkVisualStep; label: string }[] = [
  { id: "pending", label: "Pending" },
  { id: "processing", label: "Processing" },
  { id: "confirming", label: "Confirming" },
];

function stepIndex(step: StkVisualStep): number {
  if (step === "failed" || step === "complete") {
    return 2;
  }
  return STEPS.findIndex((entry) => entry.id === step);
}

function stepState(
  stepId: StkVisualStep,
  activeStep: StkVisualStep,
): "completed" | "current" | "upcoming" {
  const activeIndex = stepIndex(activeStep);
  const index = STEPS.findIndex((entry) => entry.id === stepId);

  if (index < activeIndex) {
    return "completed";
  }
  if (index === activeIndex) {
    return "current";
  }
  return "upcoming";
}

interface PaymentStkStepIndicatorProps {
  activeStep: StkVisualStep;
}

export function PaymentStkStepIndicator({ activeStep }: PaymentStkStepIndicatorProps) {
  if (activeStep === "failed") {
    return null;
  }

  const displayStep =
    activeStep === "complete" ? ("confirming" as StkVisualStep) : activeStep;

  return (
    <ol className="flex items-center justify-between gap-2" aria-label="Payment progress">
      {STEPS.map((step, index) => {
        const state = stepState(step.id, displayStep);
        const isLast = index === STEPS.length - 1;

        return (
          <li key={step.id} className="relative flex flex-1 flex-col items-center">
            {!isLast ? (
              <span
                className={`absolute left-[calc(50%+14px)] top-3.5 h-0.5 w-[calc(100%-28px)] ${
                  state === "completed" ? "bg-[#c9a227]" : "bg-zinc-200"
                }`}
                aria-hidden
              />
            ) : null}

            <motion.span
              layout
              animate={
                state === "current"
                  ? { scale: [1, 1.08, 1] }
                  : { scale: 1 }
              }
              transition={
                state === "current"
                  ? { duration: 1.6, repeat: Infinity, ease: "easeInOut" }
                  : { duration: 0.2 }
              }
              className={`relative z-10 flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold ${
                state === "completed"
                  ? "bg-[#c9a227] text-zinc-900"
                  : state === "current"
                    ? "bg-[#c9a227] text-zinc-900 ring-4 ring-[#c9a227]/25"
                    : "bg-zinc-100 text-zinc-400"
              }`}
            >
              {state === "completed" ? "✓" : index + 1}
            </motion.span>

            <span
              className={`mt-2 text-center text-[10px] font-bold uppercase tracking-wide sm:text-[11px] ${
                state === "current"
                  ? "text-[#8b6914]"
                  : state === "completed"
                    ? "text-zinc-700"
                    : "text-zinc-400"
              }`}
            >
              {step.label}
            </span>
          </li>
        );
      })}
    </ol>
  );
}
