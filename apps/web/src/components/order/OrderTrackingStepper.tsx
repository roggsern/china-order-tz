"use client";

import { AnimatePresence, motion } from "framer-motion";
import type { CustomerProgressDisplayStep } from "@/lib/order/customer-progress";
import { resolveTrackingStepVisualState } from "@/lib/order/tracking-stepper-visual-state";
import { formatTrackingTimestamp } from "@/lib/order/tracking-format";

interface OrderTrackingStepperProps {
  timeline: CustomerProgressDisplayStep[];
  tone?: "light" | "dark";
}

function StepIndicator({
  step,
  visualState,
  isCancelled,
  tone,
}: {
  step: CustomerProgressDisplayStep;
  visualState: CustomerProgressDisplayStep["state"];
  isCancelled: boolean;
  tone: "light" | "dark";
}) {
  const ring = tone === "dark" ? "ring-zinc-950" : "ring-white";
  const base = `relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-4 ${ring} transition-colors duration-300`;

  if (isCancelled) {
    return (
      <span
        className={`${base} ${
          tone === "dark" ? "bg-zinc-800 text-zinc-500" : "bg-zinc-100 text-zinc-400"
        }`}
        aria-hidden
      >
        –
      </span>
    );
  }

  if (visualState === "completed") {
    return (
      <motion.span
        layout
        initial={{ scale: 0.85, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        className={`${base} bg-emerald-500 text-white shadow-lg shadow-emerald-500/25`}
        aria-hidden
      >
        ✓
      </motion.span>
    );
  }

  if (visualState === "current") {
    return (
      <motion.span
        layout
        animate={{
          scale: [1, 1.05, 1],
          boxShadow: [
            "0 0 0 0 rgba(201,162,39,0.45)",
            "0 0 0 10px rgba(201,162,39,0)",
            "0 0 0 0 rgba(201,162,39,0)",
          ],
        }}
        transition={{ duration: 2.2, repeat: Infinity, ease: "easeInOut" }}
        className={`${base} bg-gradient-to-br from-[#c9a227] to-[#e8c547] text-zinc-900`}
        aria-hidden
      >
        {step.icon}
      </motion.span>
    );
  }

  return (
    <span
      className={`${base} ${
        tone === "dark"
          ? "bg-zinc-800 text-zinc-500"
          : "bg-zinc-100 text-zinc-400 ring-zinc-50"
      }`}
      aria-hidden
    >
      {step.icon}
    </span>
  );
}

function connectorClass(
  visualState: CustomerProgressDisplayStep["state"],
  isCancelled: boolean,
  tone: "light" | "dark",
): string {
  if (isCancelled || visualState === "upcoming") {
    return tone === "dark" ? "bg-zinc-700" : "bg-zinc-200";
  }
  if (visualState === "completed") {
    return "bg-emerald-400";
  }
  return "bg-gradient-to-b from-[#c9a227] to-zinc-200";
}

export function OrderTrackingStepper({
  timeline,
  tone = "light",
}: OrderTrackingStepperProps) {
  const isCancelled = timeline.every((step) => step.state === "cancelled");
  const isDark = tone === "dark";

  return (
    <ol
      aria-label="Order tracking progress"
      className={`space-y-0 ${isDark ? "text-zinc-100" : "text-zinc-900"}`}
    >
      {timeline.map((step, index) => {
        const isLast = index === timeline.length - 1;
        const visualState = resolveTrackingStepVisualState(step, index, timeline.length);

        return (
          <motion.li
            key={step.key}
            layout
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: index * 0.05, duration: 0.28 }}
            className="relative flex gap-4 pb-7 last:pb-0"
          >
            {!isLast ? (
              <span
                className={`absolute left-[19px] top-10 h-[calc(100%-10px)] w-0.5 ${connectorClass(
                  visualState,
                  isCancelled,
                  tone,
                )}`}
                aria-hidden
              />
            ) : null}

            <StepIndicator
              step={step}
              visualState={visualState}
              isCancelled={isCancelled}
              tone={tone}
            />

            <div
              className={`min-w-0 flex-1 rounded-2xl px-3 py-2.5 transition sm:px-4 ${
                visualState === "current" && !isCancelled
                  ? isDark
                    ? "bg-[#c9a227]/10 ring-1 ring-[#c9a227]/25"
                    : "bg-[#c9a227]/8 ring-1 ring-[#c9a227]/20"
                  : visualState === "completed" && !isCancelled
                    ? isDark
                      ? "bg-emerald-500/5"
                      : "bg-emerald-50/50"
                    : ""
              }`}
            >
              <div className="flex flex-wrap items-center gap-2">
                <p
                  className={`text-sm font-bold ${
                    visualState === "current" && !isCancelled
                      ? isDark
                        ? "text-[#e8c547]"
                        : "text-[#8b6914]"
                      : visualState === "completed"
                        ? isDark
                          ? "text-[#c9a227]"
                          : "text-zinc-900"
                        : isDark
                          ? "text-zinc-400"
                          : "text-zinc-400"
                  }`}
                >
                  {step.label}
                </p>
                {visualState === "current" && !isCancelled ? (
                  <span
                    className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
                      isDark
                        ? "bg-[#c9a227]/15 text-[#e8c547]"
                        : "bg-[#c9a227]/15 text-[#8b6914]"
                    }`}
                  >
                    Current
                  </span>
                ) : null}
              </div>
              <p
                className={`mt-0.5 text-xs leading-relaxed ${
                  isDark ? "text-zinc-400" : "text-zinc-500"
                }`}
              >
                {step.description}
              </p>
              <AnimatePresence mode="wait">
                {step.timestamp ? (
                  <motion.p
                    key={step.timestamp}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    className={`mt-1 text-[11px] ${isDark ? "text-zinc-500" : "text-zinc-400"}`}
                  >
                    {formatTrackingTimestamp(step.timestamp)}
                  </motion.p>
                ) : null}
              </AnimatePresence>
            </div>
          </motion.li>
        );
      })}
    </ol>
  );
}

/** @deprecated Use OrderTrackingStepper — kept for legacy imports. */
export { OrderTrackingStepper as OrderTrackingTimeline };
