"use client";

import { ADMIN_LIVE_STATUS_STYLES } from "@/lib/admin/order-live-status";

const LEGEND_ITEMS = [
  { key: "paid" as const, description: "Payment confirmed" },
  { key: "processing" as const, description: "Fulfillment in progress" },
  { key: "pending" as const, description: "Awaiting payment or action" },
];

export function AdminOrderStatusLegend() {
  return (
    <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-zinc-200 bg-white px-4 py-3">
      <span className="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Status</span>
      {LEGEND_ITEMS.map(({ key, description }) => {
        const styles = ADMIN_LIVE_STATUS_STYLES[key];
        return (
          <span key={key} className="inline-flex items-center gap-1.5 text-xs text-zinc-600">
            <span
              className={`h-2 w-2 rounded-full ${styles.dot} ring-2 ${styles.ring}`}
              aria-hidden
            />
            <span className="font-semibold text-zinc-800">{styles.label}</span>
            <span className="hidden text-zinc-500 sm:inline">— {description}</span>
          </span>
        );
      })}
    </div>
  );
}
