"use client";

import { motion } from "framer-motion";
import {
  getStkStepIndex,
  STK_FLOW_STEPS,
  type StkFlowPhase,
} from "@/lib/payment/stk-flow";

interface MpesaStkFlowStepsProps {
  phase: StkFlowPhase;
}

function stepStatus(
  stepIndex: number,
  activeIndex: number,
): "completed" | "current" | "upcoming" {
  if (activeIndex < 0) {
    return "upcoming";
  }
  if (activeIndex >= STK_FLOW_STEPS.length) {
    return "completed";
  }
  if (stepIndex < activeIndex) {
    return "completed";
  }
  if (stepIndex === activeIndex) {
    return "current";
  }
  return "upcoming";
}

export function MpesaStkFlowSteps({ phase }: MpesaStkFlowStepsProps) {
  if (phase === "failed") {
    return null;
  }

  const activeIndex = getStkStepIndex(phase);

  return (
    <ol className="space-y-0" aria-label="M-Pesa payment progress">
      {STK_FLOW_STEPS.map((step, index) => {
        const status = stepStatus(index, activeIndex);
        const isLast = index === STK_FLOW_STEPS.length - 1;

        return (
          <li key={step.id} className="relative flex gap-4 pb-6 last:pb-0">
            {!isLast ? (
              <span
                className={`absolute left-[15px] top-8 h-[calc(100%-12px)] w-0.5 ${
                  status === "completed" ? "bg-[#c9a227]" : "bg-zinc-700"
                }`}
                aria-hidden
              />
            ) : null}

            <div className="relative z-10 shrink-0">
              <motion.span
                layout
                animate={
                  status === "current"
                    ? { boxShadow: ["0 0 0 0 rgba(201,162,39,0.4)", "0 0 0 8px rgba(201,162,39,0)", "0 0 0 0 rgba(201,162,39,0)"] }
                    : {}
                }
                transition={
                  status === "current"
                    ? { duration: 1.8, repeat: Infinity, ease: "easeOut" }
                    : { duration: 0.25 }
                }
                className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors ${
                  status === "completed"
                    ? "bg-[#c9a227] text-zinc-950"
                    : status === "current"
                      ? "bg-[#c9a227] text-zinc-950 ring-2 ring-[#c9a227]/50 ring-offset-2 ring-offset-zinc-950"
                      : "bg-zinc-800 text-zinc-500"
                }`}
              >
                {status === "completed" ? (
                  <motion.span initial={{ scale: 0 }} animate={{ scale: 1 }} transition={{ type: "spring", stiffness: 400 }}>
                    ✓
                  </motion.span>
                ) : (
                  index + 1
                )}
              </motion.span>
            </div>

            <div className="min-w-0 flex-1 pt-0.5">
              <motion.p
                animate={{ opacity: status === "upcoming" ? 0.45 : 1 }}
                className={`text-sm font-semibold ${
                  status === "current"
                    ? "text-[#e8c547]"
                    : status === "completed"
                      ? "text-zinc-200"
                      : "text-zinc-500"
                }`}
              >
                {step.label}
              </motion.p>
              {status === "current" ? (
                <motion.div
                  initial={{ opacity: 0, width: 0 }}
                  animate={{ opacity: 1, width: "100%" }}
                  className="mt-2 h-1 overflow-hidden rounded-full bg-zinc-800"
                >
                  <motion.span
                    className="block h-full rounded-full bg-gradient-to-r from-[#8b6914] via-[#c9a227] to-[#e8c547]"
                    animate={{ x: ["-100%", "100%"] }}
                    transition={{ duration: 1.4, repeat: Infinity, ease: "linear" }}
                    style={{ width: "40%" }}
                  />
                </motion.div>
              ) : null}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
