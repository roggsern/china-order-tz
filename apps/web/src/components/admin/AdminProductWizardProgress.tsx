"use client";

import { motion } from "framer-motion";
import type {
  ProductCreationWizardStep,
  ProductCreationWizardStepId,
  ProductCreationWizardStepStatus,
} from "@/lib/admin/product-creation-wizard";
import {
  wizardStepStatusGlyph,
  wizardStepStatusLabel,
} from "@/lib/admin/product-creation-wizard";

type AdminProductWizardProgressProps = {
  steps: ProductCreationWizardStep[];
  currentStepId: string;
  percent: number;
  missingSummary: string[];
  stepStatuses: Record<ProductCreationWizardStepId, ProductCreationWizardStepStatus>;
  canSelectStep: (stepId: ProductCreationWizardStepId) => boolean;
  onSelectStep: (stepId: ProductCreationWizardStepId) => void;
};

function statusTone(status: ProductCreationWizardStepStatus, isCurrent: boolean): string {
  if (isCurrent) {
    return "border-[#c9a227] bg-[#fff8e7] text-zinc-900 shadow-sm";
  }
  switch (status) {
    case "complete":
      return "border-emerald-200 bg-emerald-50 text-emerald-900";
    case "incomplete":
      return "border-amber-200 bg-amber-50 text-amber-950";
    default:
      return "border-zinc-200 bg-white text-zinc-500";
  }
}

export function AdminProductWizardProgress({
  steps,
  currentStepId,
  percent,
  missingSummary,
  stepStatuses,
  canSelectStep,
  onSelectStep,
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

      <nav
        className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:flex xl:flex-wrap"
        aria-label="Product wizard sections"
      >
        {steps.map((step) => {
          const status = stepStatuses[step.id] ?? "not_started";
          const isCurrent = step.id === currentStepId;
          const selectable = canSelectStep(step.id);
          const label = `${wizardStepStatusGlyph(status)} ${wizardStepStatusLabel(status)}`;
          const className = `min-w-0 rounded-lg border px-2.5 py-2 text-left transition xl:min-w-[7.5rem] xl:flex-1 ${statusTone(
            status,
            isCurrent,
          )} ${
            selectable
              ? "cursor-pointer hover:brightness-[0.98] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#c9a227]"
              : "cursor-not-allowed opacity-55"
          }`;

          if (selectable) {
            return (
              <button
                key={step.id}
                type="button"
                className={className}
                aria-current={isCurrent ? "step" : undefined}
                aria-label={`${step.label}, ${wizardStepStatusLabel(status)}`}
                onClick={() => onSelectStep(step.id)}
              >
                <span className="block truncate text-[11px] font-semibold">{step.label}</span>
                <span className="mt-1 block text-[10px] font-medium tracking-wide">{label}</span>
                <span className="mt-2 block h-1 overflow-hidden rounded-full bg-zinc-200/80">
                  <motion.div
                    className={`h-full rounded-full ${
                      status === "complete"
                        ? "bg-emerald-500"
                        : status === "incomplete" || isCurrent
                          ? "bg-[#c9a227]"
                          : "bg-transparent"
                    }`}
                    initial={false}
                    animate={{
                      width:
                        status === "complete" || status === "incomplete" || isCurrent
                          ? "100%"
                          : "0%",
                    }}
                    transition={{ duration: 0.3, ease: "easeOut" }}
                  />
                </span>
              </button>
            );
          }

          return (
            <div
              key={step.id}
              className={className}
              aria-disabled="true"
              title="Save a draft to unlock this section"
            >
              <span className="block truncate text-[11px] font-semibold">{step.label}</span>
              <span className="mt-1 block text-[10px] font-medium tracking-wide">{label}</span>
              <span className="mt-2 block h-1 overflow-hidden rounded-full bg-zinc-200/80">
                <span className="block h-full w-0 rounded-full bg-transparent" />
              </span>
            </div>
          );
        })}
      </nav>

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
