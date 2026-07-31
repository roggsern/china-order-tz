"use client";

import { motion } from "framer-motion";
import type { ProductCreationWizardStep } from "@/lib/admin/product-creation-wizard";

type AdminProductWizardProgressProps = {
  steps: ProductCreationWizardStep[];
  currentStepId: string;
  percent: number;
  missingSummary: string[];
};

export function AdminProductWizardProgress({
  steps,
  currentStepId,
  percent,
  missingSummary,
}: AdminProductWizardProgressProps) {
  const currentIndex = Math.max(
    0,
    steps.findIndex((step) => step.id === currentStepId),
  );

  return (
    <div className="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#8b6914]">
            Product draft
          </p>
          <p className="mt-1 text-sm font-semibold text-zinc-900">
            Step {currentIndex + 1} of {steps.length}: {steps[currentIndex]?.label}
          </p>
        </div>
        <div className="text-right">
          <p className="text-2xl font-bold text-zinc-900">{percent}%</p>
          <p className="text-xs text-zinc-500">complete</p>
        </div>
      </div>

      <div className="mt-4 flex gap-2">
        {steps.map((step, index) => {
          const isComplete = index < currentIndex;
          const isCurrent = step.id === currentStepId;

          return (
            <div key={step.id} className="min-w-0 flex-1">
              <div className="h-1.5 overflow-hidden rounded-full bg-zinc-200">
                <motion.div
                  className={`h-full rounded-full ${
                    isComplete || isCurrent ? "bg-[#c9a227]" : "bg-transparent"
                  }`}
                  initial={false}
                  animate={{ width: isComplete || isCurrent ? "100%" : "0%" }}
                  transition={{ duration: 0.35, ease: "easeOut" }}
                />
              </div>
              <p
                className={`mt-2 truncate text-[11px] font-medium ${
                  isCurrent ? "text-zinc-900" : isComplete ? "text-zinc-600" : "text-zinc-400"
                }`}
                title={step.label}
              >
                {step.label}
              </p>
            </div>
          );
        })}
      </div>

      {missingSummary.length > 0 ? (
        <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
          <p className="text-xs font-semibold text-amber-900">Missing before publish</p>
          <ul className="mt-1 list-inside list-disc text-xs text-amber-800">
            {missingSummary.slice(0, 6).map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
