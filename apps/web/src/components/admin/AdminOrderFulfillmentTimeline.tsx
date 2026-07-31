"use client";

import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import {
  ADMIN_FULFILMENT_STATUS_STEPS,
  resolveAdminFulfilmentStatusStep,
  resolveStatusTrackStates,
} from "@/lib/admin/order-detail-display";

interface AdminOrderFulfillmentTimelineProps {
  order: Order;
  className?: string;
}

/** Read-only fulfilment progress — payment is shown separately. */
export function AdminOrderFulfillmentTimeline({ order, className = "" }: AdminOrderFulfillmentTimelineProps) {
  const activeStep = resolveAdminFulfilmentStatusStep(order);
  const states = resolveStatusTrackStates(
    ADMIN_FULFILMENT_STATUS_STEPS,
    activeStep,
    order.status === ORDER_STATUS.CANCELLED,
  );
  const isCancelled = order.status === ORDER_STATUS.CANCELLED;

  return (
    <div className={className} aria-label="Fulfilment progress">
      <ol className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-2">
        {ADMIN_FULFILMENT_STATUS_STEPS.map((step, index) => {
          const state = states[index]!;
          const isLast = index === ADMIN_FULFILMENT_STATUS_STEPS.length - 1;

          return (
            <li
              key={step.id}
              className="relative flex flex-1 items-center gap-3 sm:flex-col sm:gap-2 sm:text-center"
            >
              {!isLast && (
                <span
                  className="absolute left-4 top-8 hidden h-px w-[calc(100%-2rem)] bg-zinc-200 sm:left-[calc(50%+1rem)] sm:top-4 sm:block sm:h-0.5 sm:w-[calc(100%-2rem)]"
                  aria-hidden
                />
              )}

              <span
                className={`relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold sm:mx-auto ${
                  state === "completed"
                    ? "bg-emerald-100 text-emerald-700 ring-2 ring-emerald-200"
                    : state === "current"
                      ? "bg-[#c9a227]/20 text-[#8b6914] ring-2 ring-[#c9a227]/40"
                      : state === "cancelled"
                        ? "bg-red-50 text-red-500 ring-2 ring-red-100"
                        : "bg-zinc-100 text-zinc-400 ring-2 ring-zinc-200"
                }`}
              >
                {state === "completed" ? "✓" : index + 1}
              </span>

              <div className="min-w-0 sm:px-1">
                <p
                  className={`text-xs font-semibold sm:text-[11px] ${
                    state === "current"
                      ? "text-[#8b6914]"
                      : state === "completed"
                        ? "text-zinc-800"
                        : state === "cancelled"
                          ? "text-red-600"
                          : "text-zinc-400"
                  }`}
                >
                  {step.label}
                </p>
              </div>
            </li>
          );
        })}
      </ol>

      {isCancelled && (
        <p className="mt-3 text-center text-xs font-semibold text-red-600">Order cancelled</p>
      )}
    </div>
  );
}
