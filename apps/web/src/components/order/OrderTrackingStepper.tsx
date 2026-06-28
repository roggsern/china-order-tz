"use client";

import { AnimatePresence, motion } from "framer-motion";
import type { TrackingTimelineStep } from "@/lib/order/tracking-status";
import { formatTrackingTimestamp } from "@/lib/order/tracking-format";

interface OrderTrackingStepperProps {
  timeline: TrackingTimelineStep[];
}

function StepIndicator({
  step,
  isCancelled,
}: {
  step: TrackingTimelineStep;
  isCancelled: boolean;
}) {
  const base =
    "relative z-10 flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-4 ring-zinc-950 transition-colors duration-300";

  if (isCancelled) {
    return (
      <span className={`${base} bg-zinc-800 text-zinc-500 ring-zinc-900`} aria-hidden>
        {step.icon}
      </span>
    );
  }

  if (step.state === "completed") {
    return (
      <motion.span
        layout
        initial={{ scale: 0.85, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        className={`${base} bg-gradient-to-br from-[#c9a227] to-[#e8c547] text-zinc-950 shadow-lg shadow-[#c9a227]/25`}
        aria-hidden
      >
        ✓
      </motion.span>
    );
  }

  if (step.state === "current") {
    return (
      <motion.span
        layout
        animate={{ scale: [1, 1.06, 1], boxShadow: ["0 0 0 0 rgba(201,162,39,0.4)", "0 0 0 8px rgba(201,162,39,0)", "0 0 0 0 rgba(201,162,39,0)"] }}
        transition={{ duration: 2.2, repeat: Infinity, ease: "easeInOut" }}
        className={`${base} bg-zinc-900 text-[#e8c547] ring-[#c9a227]/30`}
        aria-hidden
      >
        {step.icon}
      </motion.span>
    );
  }

  return (
    <span className={`${base} bg-zinc-800 text-zinc-500 ring-zinc-900`} aria-hidden>
      {step.icon}
    </span>
  );
}

function connectorClass(step: TrackingTimelineStep, isCancelled: boolean): string {
  if (isCancelled || step.state === "upcoming") {
    return "bg-zinc-700";
  }
  if (step.state === "completed") {
    return "bg-gradient-to-r from-[#c9a227] to-[#e8c547]";
  }
  return "bg-[#c9a227]/60";
}

export function OrderTrackingStepper({ timeline }: OrderTrackingStepperProps) {
  const isCancelled = timeline.every((step) => step.state === "cancelled");

  return (
    <div aria-label="Order tracking progress" className="text-zinc-100">
      {/* Desktop horizontal stepper */}
      <ol className="hidden lg:flex lg:items-start lg:justify-between">
        {timeline.map((step, index) => {
          const isLast = index === timeline.length - 1;

          return (
            <motion.li
              key={step.status}
              layout
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.05, duration: 0.3 }}
              className="relative flex flex-1 flex-col items-center"
            >
              {!isLast ? (
                <motion.span
                  layout
                  className={`absolute left-[calc(50%+22px)] top-[22px] h-0.5 w-[calc(100%-44px)] ${connectorClass(step, isCancelled)}`}
                  aria-hidden
                  animate={{
                    scaleX: step.state === "completed" ? 1 : 0.35,
                    opacity: step.state === "upcoming" ? 0.35 : 1,
                  }}
                  style={{ transformOrigin: "left center" }}
                />
              ) : null}

              <StepIndicator step={step} isCancelled={isCancelled} />

              <p
                className={`mt-3 text-center text-[10px] font-bold uppercase tracking-wide xl:text-[11px] ${
                  step.state === "current" && !isCancelled
                    ? "text-[#e8c547]"
                    : step.state === "completed"
                      ? "text-[#c9a227]"
                      : "text-zinc-500"
                }`}
              >
                {step.label}
              </p>

              {step.timestamp ? (
                <p className="mt-1 text-center text-[9px] text-zinc-500">
                  {formatTrackingTimestamp(step.timestamp)}
                </p>
              ) : null}
            </motion.li>
          );
        })}
      </ol>

      {/* Mobile + tablet vertical timeline */}
      <ol className="space-y-0 lg:hidden">
        {timeline.map((step, index) => {
          const isLast = index === timeline.length - 1;

          return (
            <motion.li
              key={step.status}
              layout
              initial={{ opacity: 0, x: -12 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: index * 0.06, duration: 0.3 }}
              className="relative flex gap-4 pb-7 last:pb-0"
            >
              {!isLast ? (
                <span
                  className={`absolute left-[22px] top-11 h-[calc(100%-12px)] w-0.5 ${connectorClass(step, isCancelled)}`}
                  aria-hidden
                />
              ) : null}

              <StepIndicator step={step} isCancelled={isCancelled} />

              <div className="min-w-0 flex-1 pt-1">
                <div className="flex flex-wrap items-center gap-2">
                  <p
                    className={`text-sm font-bold ${
                      step.state === "current" && !isCancelled
                        ? "text-[#e8c547]"
                        : step.state === "completed"
                          ? "text-[#c9a227]"
                          : "text-zinc-300"
                    }`}
                  >
                    {step.label}
                  </p>
                  {step.state === "current" && !isCancelled ? (
                    <span className="rounded-full bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#e8c547]">
                      Current
                    </span>
                  ) : null}
                  {step.state === "completed" && !isCancelled ? (
                    <span className="rounded-full bg-[#c9a227]/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#c9a227]">
                      Done
                    </span>
                  ) : null}
                </div>
                <p className="mt-0.5 text-xs leading-relaxed text-zinc-400">{step.description}</p>
                <AnimatePresence mode="wait">
                  {step.timestamp ? (
                    <motion.p
                      key={step.timestamp}
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      className="mt-1 text-[11px] text-zinc-500"
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
    </div>
  );
}

/** @deprecated Use OrderTrackingStepper — kept for legacy imports. */
export { OrderTrackingStepper as OrderTrackingTimeline };
