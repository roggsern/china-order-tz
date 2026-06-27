"use client";

import { AnimatePresence, motion } from "framer-motion";
import type { Order } from "@/lib/types/order";
import {
  CUSTOMER_TRACKING_STAGES,
  getTrackingStageStates,
  type TrackingStageState,
} from "@/lib/order/tracking-stages";

interface OrderTrackingTimelineProps {
  order: Order;
}

function StepIndicator({
  state,
  icon,
  isCancelled,
}: {
  state: TrackingStageState;
  icon: string;
  isCancelled: boolean;
}) {
  const base =
    "relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-base ring-4 ring-white transition-colors duration-300";

  if (isCancelled) {
    return (
      <span className={`${base} bg-zinc-100 text-zinc-400 ring-zinc-50`} aria-hidden>
        {icon}
      </span>
    );
  }

  if (state === "completed") {
    return (
      <motion.span
        layout
        initial={{ scale: 0.85, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        className={`${base} bg-emerald-500 text-white`}
        aria-hidden
      >
        ✓
      </motion.span>
    );
  }

  if (state === "current") {
    return (
      <motion.span
        layout
        animate={{ scale: [1, 1.06, 1] }}
        transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
        className={`${base} bg-[#c9a227] text-zinc-900 shadow-lg shadow-[#c9a227]/30`}
        aria-hidden
      >
        {icon}
      </motion.span>
    );
  }

  return (
    <span className={`${base} bg-zinc-100 text-zinc-400`} aria-hidden>
      {icon}
    </span>
  );
}

function connectorClass(state: TrackingStageState, isCancelled: boolean): string {
  if (isCancelled || state === "upcoming") {
    return "bg-zinc-200";
  }
  return "bg-emerald-400";
}

export function OrderTrackingTimeline({ order }: OrderTrackingTimelineProps) {
  const stageStates = getTrackingStageStates(order);
  const isCancelled = order.status === "cancelled";

  return (
    <div aria-label="Order tracking progress">
      {/* Desktop: horizontal stepper */}
      <ol className="hidden sm:flex sm:items-start sm:justify-between">
        {CUSTOMER_TRACKING_STAGES.map((stage, index) => {
          const state = stageStates[index];
          const isLast = index === CUSTOMER_TRACKING_STAGES.length - 1;

          return (
            <motion.li
              key={stage.id}
              layout
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.05, duration: 0.25 }}
              className="relative flex flex-1 flex-col items-center"
            >
              {!isLast && (
                <motion.span
                  layout
                  className={`absolute left-[calc(50%+20px)] top-5 h-0.5 w-[calc(100%-40px)] ${connectorClass(state, isCancelled)}`}
                  aria-hidden
                  animate={{
                    scaleX: state === "completed" ? 1 : 0.3,
                    opacity: state === "completed" ? 1 : 0.5,
                  }}
                  style={{ transformOrigin: "left center" }}
                />
              )}

              <StepIndicator state={state} icon={stage.icon} isCancelled={isCancelled} />

              <p
                className={`mt-3 text-center text-[11px] font-bold leading-tight xl:text-xs ${
                  state === "current" && !isCancelled
                    ? "text-[#8b6914]"
                    : state === "completed"
                      ? "text-emerald-700"
                      : "text-zinc-400"
                }`}
              >
                {stage.label}
              </p>

              <AnimatePresence mode="wait">
                {state === "current" && !isCancelled ? (
                  <motion.p
                    key="current-desc"
                    initial={{ opacity: 0, height: 0 }}
                    animate={{ opacity: 1, height: "auto" }}
                    exit={{ opacity: 0, height: 0 }}
                    className="mt-1 hidden max-w-[7rem] text-center text-[10px] leading-snug text-zinc-500 xl:block"
                  >
                    {stage.description}
                  </motion.p>
                ) : null}
              </AnimatePresence>
            </motion.li>
          );
        })}
      </ol>

      {/* Mobile: vertical timeline */}
      <ol className="space-y-0 sm:hidden">
        {CUSTOMER_TRACKING_STAGES.map((stage, index) => {
          const state = stageStates[index];
          const isLast = index === CUSTOMER_TRACKING_STAGES.length - 1;

          return (
            <motion.li
              key={stage.id}
              layout
              initial={{ opacity: 0, x: -12 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: index * 0.06, duration: 0.25 }}
              className="relative flex gap-4 pb-6 last:pb-0"
            >
              {!isLast && (
                <span
                  className={`absolute left-5 top-10 h-[calc(100%-16px)] w-0.5 ${connectorClass(state, isCancelled)}`}
                  aria-hidden
                />
              )}

              <StepIndicator state={state} icon={stage.icon} isCancelled={isCancelled} />

              <div className="min-w-0 flex-1 pt-1.5">
                <div className="flex flex-wrap items-center gap-2">
                  <p
                    className={`text-sm font-bold ${
                      state === "current" && !isCancelled ? "text-[#8b6914]" : "text-zinc-900"
                    }`}
                  >
                    {stage.label}
                  </p>
                  {state === "current" && !isCancelled ? (
                    <span className="rounded-full bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#8b6914]">
                      Current
                    </span>
                  ) : null}
                  {state === "completed" && !isCancelled ? (
                    <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                      Done
                    </span>
                  ) : null}
                </div>
                <p className="mt-0.5 text-xs leading-relaxed text-zinc-500">{stage.description}</p>
              </div>
            </motion.li>
          );
        })}
      </ol>
    </div>
  );
}
