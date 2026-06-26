"use client";

import type { Order } from "@/lib/types/order";
import {
  CUSTOMER_TRACKING_STAGES,
  getTrackingStageStates,
} from "@/lib/order/tracking-stages";

interface OrderTrackingTimelineProps {
  order: Order;
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
            <li key={stage.id} className="relative flex flex-1 flex-col items-center">
              {!isLast && (
                <span
                  className={`absolute left-[calc(50%+20px)] top-5 h-0.5 w-[calc(100%-40px)] ${
                    state === "completed" ? "bg-emerald-400" : "bg-zinc-200"
                  }`}
                  aria-hidden
                />
              )}

              <span
                className={`relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-base ring-4 ring-white ${
                  isCancelled
                    ? "bg-zinc-100 text-zinc-400 ring-zinc-50"
                    : state === "completed"
                      ? "bg-emerald-500 text-white"
                      : state === "current"
                        ? "bg-[#c9a227] text-zinc-900 shadow-lg shadow-[#c9a227]/30"
                        : "bg-zinc-100 text-zinc-400"
                }`}
                aria-hidden
              >
                {state === "completed" && !isCancelled ? "✓" : stage.icon}
              </span>

              <p
                className={`mt-3 text-center text-xs font-bold ${
                  state === "current" && !isCancelled
                    ? "text-[#8b6914]"
                    : state === "completed"
                      ? "text-emerald-700"
                      : "text-zinc-400"
                }`}
              >
                {stage.label}
              </p>
            </li>
          );
        })}
      </ol>

      {/* Mobile: vertical timeline */}
      <ol className="space-y-0 sm:hidden">
        {CUSTOMER_TRACKING_STAGES.map((stage, index) => {
          const state = stageStates[index];
          const isLast = index === CUSTOMER_TRACKING_STAGES.length - 1;

          return (
            <li key={stage.id} className="relative flex gap-4 pb-6 last:pb-0">
              {!isLast && (
                <span
                  className={`absolute left-5 top-10 h-[calc(100%-16px)] w-0.5 ${
                    state === "completed" && !isCancelled ? "bg-emerald-400" : "bg-zinc-200"
                  }`}
                  aria-hidden
                />
              )}

              <span
                className={`relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm ${
                  isCancelled
                    ? "bg-zinc-100 text-zinc-400"
                    : state === "completed"
                      ? "bg-emerald-500 text-white"
                      : state === "current"
                        ? "bg-[#c9a227] text-zinc-900 ring-2 ring-[#c9a227]/40"
                        : "bg-zinc-100 text-zinc-400"
                }`}
                aria-hidden
              >
                {state === "completed" && !isCancelled ? "✓" : stage.icon}
              </span>

              <div className="min-w-0 flex-1 pt-1.5">
                <p
                  className={`text-sm font-bold ${
                    state === "current" && !isCancelled ? "text-[#8b6914]" : "text-zinc-900"
                  }`}
                >
                  {stage.label}
                </p>
                <p className="mt-0.5 text-xs leading-relaxed text-zinc-500">{stage.description}</p>
              </div>
            </li>
          );
        })}
      </ol>
    </div>
  );
}
