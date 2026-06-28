"use client";

import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

type StepState = "completed" | "current" | "upcoming" | "cancelled";

type FulfillmentStep = {
  id: string;
  label: string;
};

const ADMIN_FULFILLMENT_STEPS: FulfillmentStep[] = [
  { id: "pending", label: "Pending" },
  { id: "paid", label: "Paid" },
  { id: "processing", label: "Processing" },
  { id: "shipped", label: "Shipped" },
  { id: "delivered", label: "Delivered" },
];

function isPaid(order: Order): boolean {
  return order.paymentStatus === PAYMENT_STATUS.PAID;
}

function getActiveStepIndex(order: Order): number {
  if (order.status === ORDER_STATUS.DELIVERED) {
    return ADMIN_FULFILLMENT_STEPS.length;
  }
  if (
    order.status === ORDER_STATUS.SHIPPED ||
    order.status === ORDER_STATUS.IN_TRANSIT
  ) {
    return 3;
  }
  if (order.status === ORDER_STATUS.PROCESSING || order.status === ORDER_STATUS.PACKED) {
    return 2;
  }
  if (isPaid(order)) {
    return 1;
  }
  return 0;
}

function resolveStepStates(order: Order): StepState[] {
  if (order.status === ORDER_STATUS.CANCELLED) {
    return ADMIN_FULFILLMENT_STEPS.map(() => "cancelled");
  }

  const activeIndex = getActiveStepIndex(order);
  if (activeIndex >= ADMIN_FULFILLMENT_STEPS.length) {
    return ADMIN_FULFILLMENT_STEPS.map(() => "completed");
  }

  return ADMIN_FULFILLMENT_STEPS.map((_, index) => {
    if (index < activeIndex) {
      return "completed";
    }
    if (index === activeIndex) {
      return "current";
    }
    return "upcoming";
  });
}

interface AdminOrderFulfillmentTimelineProps {
  order: Order;
  className?: string;
}

export function AdminOrderFulfillmentTimeline({ order, className = "" }: AdminOrderFulfillmentTimelineProps) {
  const states = resolveStepStates(order);
  const isCancelled = order.status === ORDER_STATUS.CANCELLED;

  return (
    <div className={className} aria-label="Order fulfillment progress">
      <ol className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-2">
        {ADMIN_FULFILLMENT_STEPS.map((step, index) => {
          const state = states[index]!;
          const isLast = index === ADMIN_FULFILLMENT_STEPS.length - 1;

          return (
            <li key={step.id} className="relative flex flex-1 items-center gap-3 sm:flex-col sm:gap-2 sm:text-center">
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
                {state === "current" && !isCancelled && (
                  <p className="mt-0.5 text-[10px] font-medium text-zinc-500 sm:hidden">Current step</p>
                )}
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
