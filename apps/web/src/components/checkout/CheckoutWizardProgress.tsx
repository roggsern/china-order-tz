"use client";

import { motion } from "framer-motion";

const STEPS = [
  { id: 1, label: "Information" },
  { id: 2, label: "Shipping" },
  { id: 3, label: "Summary" },
] as const;

interface CheckoutWizardProgressProps {
  currentStep: 1 | 2 | 3;
}

export function CheckoutWizardProgress({ currentStep }: CheckoutWizardProgressProps) {
  return (
    <div className="mt-6">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#8b6914]">
          Step {currentStep} of 3
        </p>
        <p className="text-xs font-medium text-zinc-500">
          {STEPS.find((step) => step.id === currentStep)?.label}
        </p>
      </div>

      <div className="mt-3 flex gap-2">
        {STEPS.map((step) => {
          const isComplete = step.id < currentStep;
          const isCurrent = step.id === currentStep;

          return (
            <div key={step.id} className="flex-1">
              <div className="h-1.5 overflow-hidden rounded-full bg-zinc-100">
                <motion.div
                  className={`h-full rounded-full ${
                    isComplete || isCurrent ? "bg-[#c9a227]" : "bg-transparent"
                  }`}
                  initial={false}
                  animate={{ width: isComplete ? "100%" : isCurrent ? "100%" : "0%" }}
                  transition={{ duration: 0.35, ease: "easeOut" }}
                />
              </div>
              <p
                className={`mt-2 hidden text-[11px] font-medium sm:block ${
                  isCurrent ? "text-zinc-900" : isComplete ? "text-zinc-600" : "text-zinc-400"
                }`}
              >
                {step.label}
              </p>
            </div>
          );
        })}
      </div>
    </div>
  );
}
