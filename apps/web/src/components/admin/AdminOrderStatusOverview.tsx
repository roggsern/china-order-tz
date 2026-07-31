"use client";

import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import {
  ADMIN_FULFILMENT_STATUS_STEPS,
  ADMIN_PAYMENT_STATUS_STEPS,
  resolveAdminFulfilmentStatusStep,
  resolveAdminFulfilmentStatusLabel,
  resolveAdminPaymentStatusLabel,
  resolveAdminPaymentStatusStep,
  resolveStatusTrackStates,
  type AdminPaymentStatusStepId,
  type StatusTrackState,
} from "@/lib/admin/order-detail-display";

interface AdminOrderStatusOverviewProps {
  order: Order;
}

const PAYMENT_CARD_LABELS: Record<AdminPaymentStatusStepId, string> = {
  pending_payment: "Pending",
  paid: "Paid",
  failed: "Failed",
  refunded: "Refunded",
};

const PAYMENT_CARD_STYLES: Record<
  AdminPaymentStatusStepId,
  { accent: string; active: string; inactive: string }
> = {
  pending_payment: {
    accent: "text-amber-800",
    active: "border-amber-300 bg-amber-50/90 ring-2 ring-amber-200/80 shadow-sm",
    inactive: "border-zinc-200 bg-white text-zinc-500",
  },
  paid: {
    accent: "text-emerald-800",
    active: "border-emerald-300 bg-emerald-50/90 ring-2 ring-emerald-200/80 shadow-sm",
    inactive: "border-zinc-200 bg-white text-zinc-500",
  },
  failed: {
    accent: "text-red-800",
    active: "border-red-300 bg-red-50/90 ring-2 ring-red-200/80 shadow-sm",
    inactive: "border-zinc-200 bg-white text-zinc-500",
  },
  refunded: {
    accent: "text-violet-800",
    active: "border-violet-300 bg-violet-50/90 ring-2 ring-violet-200/80 shadow-sm",
    inactive: "border-zinc-200 bg-white text-zinc-500",
  },
};

function PaymentStateCardGroup({
  activeStepId,
  currentLabel,
}: {
  activeStepId: AdminPaymentStatusStepId;
  currentLabel: string;
}) {
  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-zinc-500">
          Payment Status
        </h3>
        <span className="rounded-full bg-zinc-100 px-2.5 py-1 text-[11px] font-semibold text-zinc-700">
          {currentLabel}
        </span>
      </div>

      <div
        className="mt-3 grid grid-cols-1 gap-2 min-[480px]:grid-cols-2 lg:grid-cols-4"
        role="group"
        aria-label={`Payment status: ${currentLabel}`}
      >
        {ADMIN_PAYMENT_STATUS_STEPS.map((step) => {
          const isActive = step.id === activeStepId;
          const styles = PAYMENT_CARD_STYLES[step.id];
          const label = PAYMENT_CARD_LABELS[step.id];

          return (
            <div
              key={step.id}
              aria-current={isActive ? "true" : undefined}
              className={`rounded-xl border px-3 py-3 transition ${
                isActive ? styles.active : styles.inactive
              }`}
            >
              <div className="flex items-center justify-between gap-2">
                <p
                  className={`text-sm font-semibold ${
                    isActive ? styles.accent : "text-zinc-500"
                  }`}
                >
                  {label}
                </p>
                {isActive ? (
                  <span
                    className={`inline-flex h-2 w-2 shrink-0 rounded-full ${
                      step.id === "pending_payment"
                        ? "bg-amber-500"
                        : step.id === "paid"
                          ? "bg-emerald-500"
                          : step.id === "failed"
                            ? "bg-red-500"
                            : "bg-violet-500"
                    }`}
                    aria-hidden
                  />
                ) : null}
              </div>
              {isActive ? (
                <p className="mt-1 text-[10px] font-medium uppercase tracking-wide text-zinc-500">
                  Current state
                </p>
              ) : null}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function FulfilmentStatusTrack({
  currentLabel,
  steps,
  states,
  cancelled,
}: {
  currentLabel: string;
  steps: ReadonlyArray<{ id: string; label: string }>;
  states: StatusTrackState[];
  cancelled?: boolean;
}) {
  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-zinc-500">
          Fulfilment Status
        </h3>
        <span className="rounded-full bg-zinc-100 px-2.5 py-1 text-[11px] font-semibold text-zinc-700">
          {currentLabel}
        </span>
      </div>

      <ol
        className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-2"
        aria-label="Fulfilment status progress"
      >
        {steps.map((step, index) => {
          const state = states[index] ?? "upcoming";
          const isLast = index === steps.length - 1;

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
                className={`relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold sm:mx-auto ${
                  state === "completed"
                    ? "bg-emerald-100 text-emerald-700 ring-2 ring-emerald-200"
                    : state === "current"
                      ? "bg-[#c9a227]/20 text-[#8b6914] ring-2 ring-[#c9a227]/40"
                      : state === "cancelled"
                        ? "bg-red-50 text-red-500 ring-2 ring-red-100"
                        : "bg-zinc-100 text-zinc-400 ring-2 ring-zinc-200"
                }`}
              >
                {state === "completed" ? "✓" : state === "current" ? "●" : index + 1}
              </span>

              <p
                className={`min-w-0 text-[11px] font-semibold sm:px-1 ${
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
            </li>
          );
        })}
      </ol>

      {cancelled ? (
        <p className="mt-2 text-xs font-semibold text-red-600">Order cancelled</p>
      ) : null}
    </div>
  );
}

export function AdminOrderStatusOverview({ order }: AdminOrderStatusOverviewProps) {
  const paymentStep = resolveAdminPaymentStatusStep(order);
  const fulfilmentStep = resolveAdminFulfilmentStatusStep(order);
  const isCancelled = order.status === ORDER_STATUS.CANCELLED;

  const fulfilmentStates = resolveStatusTrackStates(
    ADMIN_FULFILMENT_STATUS_STEPS,
    fulfilmentStep,
    isCancelled,
  );

  return (
    <div className="space-y-6">
      <PaymentStateCardGroup
        activeStepId={paymentStep}
        currentLabel={resolveAdminPaymentStatusLabel(order)}
      />

      <div className="border-t border-zinc-100 pt-6">
        <FulfilmentStatusTrack
          currentLabel={resolveAdminFulfilmentStatusLabel(order)}
          steps={ADMIN_FULFILMENT_STATUS_STEPS}
          states={fulfilmentStates}
          cancelled={isCancelled}
        />
      </div>
    </div>
  );
}
