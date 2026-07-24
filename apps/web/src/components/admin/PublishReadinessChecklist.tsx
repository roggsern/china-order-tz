"use client";

import type { ProductPublishReadinessResult } from "@/lib/admin/product-publish-readiness";

type PublishReadinessChecklistProps = {
  readiness: ProductPublishReadinessResult;
  showWarning?: boolean;
};

export function PublishReadinessChecklist({
  readiness,
  showWarning = false,
}: PublishReadinessChecklistProps) {
  return (
    <div className="rounded-lg border border-zinc-200 bg-zinc-50/80 p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-sm font-semibold text-zinc-900">Publish readiness</h3>
        <span
          className={`rounded px-2 py-0.5 text-[11px] font-semibold uppercase ${
            readiness.ready
              ? "bg-emerald-50 text-emerald-700"
              : "bg-amber-50 text-amber-700"
          }`}
        >
          {readiness.ready ? "Ready" : "Incomplete"}
        </span>
      </div>
      <p className="mt-1 text-xs text-zinc-500">
        Guidance only. Backend publish validation still applies when setting lifecycle to Active.
      </p>
      {showWarning && !readiness.ready ? (
        <p className="mt-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
          Complete the missing requirements before setting lifecycle status to Active.
        </p>
      ) : null}
      <ul className="mt-3 space-y-1.5">
        {readiness.items.map((item) => (
          <li key={item.id} className="flex items-start gap-2 text-sm">
            <span
              aria-hidden
              className={item.met ? "text-emerald-600" : "text-red-600"}
            >
              {item.met ? "✓" : "✗"}
            </span>
            <span className={item.met ? "text-zinc-700" : "text-zinc-900"}>{item.label}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
