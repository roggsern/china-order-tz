"use client";

import Link from "next/link";
import {
  catalogHealthEmptyMessage,
  catalogHealthProductHref,
  catalogHealthSeverityBadgeClass,
  groupCatalogHealthIssues,
  type CatalogHealthGroupView,
  type CatalogHealthMetricView,
} from "@/lib/admin/catalog-health";

type CatalogHealthIssueCardsProps = {
  groups: CatalogHealthGroupView[];
  isEmpty: boolean;
};

function MetricCard({ metric }: { metric: CatalogHealthMetricView }) {
  const sampleIds = metric.productIds.length > 0 ? metric.productIds : [];

  return (
    <article className="rounded-xl border border-zinc-200 bg-white px-4 py-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 className="text-sm font-semibold text-zinc-900">{metric.label}</h3>
          <p className="mt-1 text-xs text-zinc-500">
            {metric.count} affected {metric.productIds.length > 0 ? "product" : "variant"}
            {metric.count === 1 ? "" : "s"}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-1.5">
          <span
            className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${catalogHealthSeverityBadgeClass(metric.severity)}`}
          >
            {metric.severity}
          </span>
          <span className="inline-flex rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-600">
            {metric.priority}
          </span>
        </div>
      </div>

      {sampleIds.length > 0 ? (
        <div className="mt-3 flex flex-wrap gap-1.5">
          {sampleIds.slice(0, 8).map((productId) => (
            <Link
              key={productId}
              href={catalogHealthProductHref(productId, metric.editTab)}
              className="inline-flex max-w-full truncate rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 font-mono text-[10px] text-zinc-700 transition hover:border-zinc-300 hover:bg-white"
              title="Open product"
            >
              {productId.slice(0, 8)}…
            </Link>
          ))}
          {metric.count > sampleIds.length ? (
            <span className="inline-flex items-center px-1 text-[10px] text-zinc-500">
              +{metric.count - sampleIds.length} more
            </span>
          ) : null}
        </div>
      ) : metric.variantIds.length > 0 ? (
        <p className="mt-3 text-xs text-zinc-500">
          {metric.variantIds.length} sample variant id
          {metric.variantIds.length === 1 ? "" : "s"} reported. Open Products → Variants to
          inspect.
        </p>
      ) : null}

      <div className="mt-3">
        <Link
          href={
            sampleIds[0]
              ? catalogHealthProductHref(sampleIds[0], metric.editTab)
              : "/admin/products"
          }
          className="text-xs font-semibold text-zinc-800 underline-offset-2 hover:underline"
        >
          Review in products
        </Link>
      </div>
    </article>
  );
}

export function CatalogHealthIssueCards({ groups, isEmpty }: CatalogHealthIssueCardsProps) {
  if (isEmpty) {
    return (
      <section className="rounded-xl border border-emerald-200 bg-emerald-50/50 px-5 py-8 text-center shadow-sm">
        <p className="text-sm font-semibold text-emerald-800">All clear</p>
        <p className="mt-1 text-sm text-emerald-700/80">{catalogHealthEmptyMessage()}</p>
      </section>
    );
  }

  const visibleGroups = groupCatalogHealthIssues(groups).filter(
    (group) => group.metrics.length > 0,
  );

  return (
    <div className="space-y-6">
      {visibleGroups.map((group) => (
        <section key={group.id} className="space-y-3">
          <div>
            <h2 className="text-sm font-semibold text-zinc-900">{group.title}</h2>
            <p className="mt-0.5 text-xs text-zinc-500">{group.description}</p>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            {group.metrics.map((metricRow) => (
              <MetricCard key={metricRow.key} metric={metricRow} />
            ))}
          </div>
        </section>
      ))}
    </div>
  );
}
