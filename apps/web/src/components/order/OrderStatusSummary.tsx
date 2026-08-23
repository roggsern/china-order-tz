"use client";

import type { CompactOrderStatusSummary } from "@/lib/order/order-status-summary";

interface OrderStatusSummaryProps {
  summary: CompactOrderStatusSummary;
}

export function OrderStatusSummary({ summary }: OrderStatusSummaryProps) {
  return (
    <div className="space-y-4">
      {summary.completedLines.length > 0 ? (
        <ul className="space-y-2.5" aria-label="Completed order milestones">
          {summary.completedLines.map((line) => (
            <li key={line.label} className="flex items-start gap-2.5 text-sm text-zinc-700">
              {line.completed ? (
                <span
                  className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20"
                  aria-hidden
                >
                  ✓
                </span>
              ) : (
                <span
                  className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-600/20"
                  aria-hidden
                >
                  •
                </span>
              )}
              <span className="font-medium text-zinc-900">{line.label}</span>
            </li>
          ))}
        </ul>
      ) : null}

      <div className="rounded-2xl border border-zinc-100 bg-zinc-50/80 p-4">
        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
          Current status
        </p>
        <p className="mt-2 text-base font-semibold text-zinc-900">{summary.currentStatus}</p>
      </div>

      {summary.currentStatus !== "Cancelled" && summary.currentStatus !== "Refunded" ? (
        <p className="text-sm text-zinc-500">
          Track your order for live shipment updates.
        </p>
      ) : null}
    </div>
  );
}
