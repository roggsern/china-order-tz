"use client";

import {
  catalogHealthScoreTone,
  type CatalogHealthReportView,
} from "@/lib/admin/catalog-health";

type CatalogHealthOverviewProps = {
  summary: CatalogHealthReportView["summary"];
};

export function CatalogHealthOverview({ summary }: CatalogHealthOverviewProps) {
  const scoreTone = catalogHealthScoreTone(summary.health_score);

  return (
    <section className="grid gap-3 sm:grid-cols-3">
      <article className="rounded-xl border border-zinc-200 bg-white px-4 py-4 shadow-sm">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
          Health score
        </p>
        <p className={`mt-2 text-3xl font-semibold tracking-tight ${scoreTone}`}>
          {summary.health_score}
        </p>
        <p className="mt-1 text-xs text-zinc-500">0–100 catalog quality index</p>
      </article>

      <article className="rounded-xl border border-red-100 bg-red-50/40 px-4 py-4 shadow-sm">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-red-700/80">
          Critical
        </p>
        <p className="mt-2 text-3xl font-semibold tracking-tight text-red-700">
          {summary.critical_count}
        </p>
        <p className="mt-1 text-xs text-red-700/70">P0 commerce and media blockers</p>
      </article>

      <article className="rounded-xl border border-amber-100 bg-amber-50/40 px-4 py-4 shadow-sm">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-amber-800/80">
          Warnings
        </p>
        <p className="mt-2 text-3xl font-semibold tracking-tight text-amber-800">
          {summary.warning_count}
        </p>
        <p className="mt-1 text-xs text-amber-800/70">P1 inventory and catalog gaps</p>
      </article>
    </section>
  );
}
