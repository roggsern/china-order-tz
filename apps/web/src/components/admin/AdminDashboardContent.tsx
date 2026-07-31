"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { AdminStatCard } from "@/components/admin/AdminStatCard";
import { AdminResponsiveTable } from "@/components/admin/AdminResponsiveTable";
import { AdminDashboardSectionShell } from "@/components/admin/AdminDashboardSectionShell";
import {
  AdminAttentionRequiredSection,
  AdminBusinessOverviewSection,
  AdminChannelSummaryCard,
  AdminChinaPipelineSection,
  AdminDashboardSection,
  AdminFulfilmentPipelineSection,
  AdminOperationsTrafficSection,
  AdminGrowthIntelligenceSection,
  AdminStorefrontConversionSection,
  AdminStorefrontTrafficSection,
  AdminTodayOverviewSection,
  AdminTzLocalPipelineSection,
} from "@/components/admin/AdminCommandCenterSections";
import type {
  AdminReportingDashboard as AdminReportingDashboardData,
  AdminReportType,
} from "@/lib/api/admin-reporting";
import {
  ADMIN_DASHBOARD_GROUPS,
  groupLabelForSection,
  type AdminDashboardSectionKey,
} from "@/lib/admin/admin-dashboard-sections";
import type {
  ChannelSummaryMetrics,
  FulfilmentPipelineMetrics,
  TodayOverviewMetrics,
} from "@/lib/admin/dashboard-command-center";
import { formatPrice } from "@/lib/catalog/utils";

type CommandCenterState =
  | { source: "api"; data: AdminReportingDashboardData }
  | {
      source: "client";
      today: TodayOverviewMetrics;
      china: ChannelSummaryMetrics;
      local: ChannelSummaryMetrics;
      pipeline: FulfilmentPipelineMetrics;
    };

type AdminDashboardContentProps = {
  data: AdminReportingDashboardData;
  commandCenter: CommandCenterState;
  sectionOrder: AdminDashboardSectionKey[];
  collapsedSections: AdminDashboardSectionKey[];
  onToggleCollapsed: (section: AdminDashboardSectionKey) => void;
  appliedFrom: string;
  appliedTo: string;
  revenueSeries: Array<{ date: string; value: number }>;
  ordersSeries: Array<{ date: string; value: number }>;
  formatWhen: (value?: string | null) => string;
  DailyBarChart: (props: {
    data: Array<{ date: string; value: number }>;
    valueKey: string;
    formatValue: (n: number) => string;
  }) => ReactNode;
  adminReportTypes: readonly AdminReportType[];
  adminReportTypeLabels: Record<AdminReportType, string>;
  exporting: string | null;
  onExport: (type: AdminReportType, format: "csv" | "xlsx") => void;
};

function sectionError(
  data: AdminReportingDashboardData,
  key: string,
): string | null {
  return data.section_errors?.[key] ?? null;
}

function SectionGroupHeader({
  section,
  previousSection,
}: {
  section: AdminDashboardSectionKey;
  previousSection: AdminDashboardSectionKey | null;
}) {
  const currentGroup = groupLabelForSection(section);
  const previousGroup = previousSection ? groupLabelForSection(previousSection) : null;

  if (currentGroup === previousGroup) {
    return null;
  }

  const group = ADMIN_DASHBOARD_GROUPS.find((entry) => entry.title === currentGroup);

  return (
    <div className="border-b border-zinc-200 pb-2 pt-2 first:pt-0">
      <h2 className="text-lg font-bold text-zinc-900">{currentGroup}</h2>
      {group?.description ? <p className="mt-1 text-sm text-zinc-500">{group.description}</p> : null}
    </div>
  );
}

export function AdminDashboardContent({
  data,
  commandCenter,
  sectionOrder,
  collapsedSections,
  onToggleCollapsed,
  appliedFrom,
  appliedTo,
  revenueSeries,
  ordersSeries,
  formatWhen,
  DailyBarChart,
  adminReportTypes,
  adminReportTypeLabels,
  exporting,
  onExport,
}: AdminDashboardContentProps) {
  let previousSection: AdminDashboardSectionKey | null = null;

  const renderSection = (section: AdminDashboardSectionKey): ReactNode => {
    const header = (
      <SectionGroupHeader section={section} previousSection={previousSection} />
    );
    previousSection = section;

    const collapsed = collapsedSections.includes(section);
    const toggle = () => onToggleCollapsed(section);

    switch (section) {
      case "attention_required":
        if (commandCenter.source === "api" && commandCenter.data.attention_items) {
          return (
            <div key={section} className="space-y-4">
              {header}
              <AdminDashboardSectionShell
                collapsed={collapsed}
                onToggleCollapsed={toggle}
                error={sectionError(data, "command_center")}
              >
                <AdminAttentionRequiredSection items={commandCenter.data.attention_items} />
              </AdminDashboardSectionShell>
            </div>
          );
        }
        if (commandCenter.source === "client") {
          return (
            <div key={section} className="space-y-4">
              {header}
              <AdminDashboardSectionShell collapsed={collapsed} onToggleCollapsed={toggle}>
                <AdminTodayOverviewSection metrics={commandCenter.today} />
              </AdminDashboardSectionShell>
            </div>
          );
        }
        return null;

      case "business_overview":
        if (commandCenter.source === "api" && commandCenter.data.overview) {
          return (
            <div key={section} className="space-y-4">
              {header}
              <AdminDashboardSectionShell
                collapsed={collapsed}
                onToggleCollapsed={toggle}
                error={sectionError(data, "command_center")}
              >
                <AdminBusinessOverviewSection metrics={commandCenter.data.overview} />
              </AdminDashboardSectionShell>
            </div>
          );
        }
        if (commandCenter.source === "client") {
          return (
            <div key={section} className="space-y-4">
              {header}
              <AdminDashboardSectionShell collapsed={collapsed} onToggleCollapsed={toggle}>
                <AdminDashboardSection
                  title="Business channels"
                  description="China import and Buy From TZ stay separate — never mixed."
                >
                  <div className="grid gap-4 lg:grid-cols-2">
                    <AdminChannelSummaryCard
                      title="China Import"
                      eyebrow="CHINA_IMPORT"
                      href="/admin/orders?source=china"
                      metrics={commandCenter.china}
                      pendingLabel="Fulfilment pending"
                    />
                    <AdminChannelSummaryCard
                      title="Buy From TZ"
                      eyebrow="TZ_LOCAL"
                      href="/admin/orders?source=local"
                      metrics={commandCenter.local}
                      pendingLabel="Delivery pending"
                    />
                  </div>
                </AdminDashboardSection>
              </AdminDashboardSectionShell>
            </div>
          );
        }
        return null;

      case "operations_traffic":
        if (commandCenter.source === "api" && commandCenter.data.operations) {
          return (
            <div key={section} className="space-y-4">
              {header}
              <AdminDashboardSectionShell
                collapsed={collapsed}
                onToggleCollapsed={toggle}
                error={sectionError(data, "command_center")}
              >
                <AdminOperationsTrafficSection metrics={commandCenter.data.operations} />
              </AdminDashboardSectionShell>
            </div>
          );
        }
        return null;

      case "china_pipeline":
        if (commandCenter.source === "api" && commandCenter.data.china_pipeline) {
          return (
            <div key={section} className="space-y-4">
              {header}
              <AdminDashboardSectionShell
                collapsed={collapsed}
                onToggleCollapsed={toggle}
                error={sectionError(data, "command_center")}
              >
                <AdminChinaPipelineSection metrics={commandCenter.data.china_pipeline} />
              </AdminDashboardSectionShell>
            </div>
          );
        }
        return null;

      case "tz_local_pipeline":
        if (commandCenter.source === "api" && commandCenter.data.tz_local) {
          return (
            <div key={section} className="space-y-4">
              {header}
              <AdminDashboardSectionShell
                collapsed={collapsed}
                onToggleCollapsed={toggle}
                error={sectionError(data, "command_center")}
              >
                <AdminTzLocalPipelineSection metrics={commandCenter.data.tz_local} />
              </AdminDashboardSectionShell>
            </div>
          );
        }
        if (commandCenter.source === "client") {
          return (
            <div key={section} className="space-y-4">
              {header}
              <AdminDashboardSectionShell collapsed={collapsed} onToggleCollapsed={toggle}>
                <AdminFulfilmentPipelineSection metrics={commandCenter.pipeline} />
              </AdminDashboardSectionShell>
            </div>
          );
        }
        return null;

      case "storefront_traffic":
        return (
          <div key={section} className="space-y-4">
            {header}
            <AdminDashboardSectionShell
              collapsed={collapsed}
              onToggleCollapsed={toggle}
              error={sectionError(data, "storefront_traffic")}
              empty={!data.storefront_traffic}
              emptyMessage="Storefront traffic data is not available for this period."
            >
              {data.storefront_traffic ? (
                <AdminStorefrontTrafficSection metrics={data.storefront_traffic} />
              ) : null}
            </AdminDashboardSectionShell>
          </div>
        );

      case "storefront_conversion":
        return (
          <div key={section} className="space-y-4">
            {header}
            <AdminDashboardSectionShell
              collapsed={collapsed}
              onToggleCollapsed={toggle}
              error={sectionError(data, "storefront_conversion")}
              empty={!data.storefront_conversion}
              emptyMessage="Conversion analytics are not available for this period."
            >
              {data.storefront_conversion ? (
                <AdminStorefrontConversionSection metrics={data.storefront_conversion} />
              ) : null}
            </AdminDashboardSectionShell>
          </div>
        );

      case "growth_intelligence":
        return (
          <div key={section} className="space-y-4">
            {header}
            <AdminDashboardSectionShell
              collapsed={collapsed}
              onToggleCollapsed={toggle}
              error={sectionError(data, "growth_intelligence")}
              empty={!data.growth_intelligence}
              emptyMessage="Growth intelligence is not available for this period."
            >
              {data.growth_intelligence ? (
                <AdminGrowthIntelligenceSection metrics={data.growth_intelligence} />
              ) : null}
            </AdminDashboardSectionShell>
          </div>
        );

      case "financial_summary":
        return (
          <div key={section} className="space-y-4">
            {header}
            <AdminDashboardSectionShell
              collapsed={collapsed}
              onToggleCollapsed={toggle}
              error={sectionError(data, "sales")}
              empty={!data.sales}
            >
              {data.sales ? (
                <AdminDashboardSection
                  title="Financial summary"
                  description={`Paid, pending, and refunded revenue for ${appliedFrom} → ${appliedTo}.`}
                >
                  <div className="admin-dashboard-grid-4">
                  <AdminStatCard
                    label="Paid revenue"
                    value={formatPrice(data.sales.paid_revenue)}
                    isText
                    accent="text-[#c9a227]"
                    variant="gold"
                  />
                  <AdminStatCard
                    label="Pending revenue"
                    value={formatPrice(data.sales.pending_revenue)}
                    isText
                    variant="default"
                  />
                  <AdminStatCard
                    label="Refunded"
                    value={formatPrice(data.sales.refunded_revenue)}
                    isText
                    accent="text-red-600"
                    variant="dark"
                  />
                  <AdminStatCard
                    label="Total (paid + pending)"
                    value={formatPrice(data.sales.total_revenue)}
                    isText
                    variant="default"
                  />
                </div>
                </AdminDashboardSection>
              ) : null}
            </AdminDashboardSectionShell>
          </div>
        );

      case "reports_trends":
        return (
          <div key={section} className="space-y-4">
            {header}
            <AdminDashboardSectionShell
              collapsed={collapsed}
              onToggleCollapsed={toggle}
              error={sectionError(data, "charts")}
            >
              <AdminDashboardSection title="Reports & trends">
              <div className="grid gap-6 xl:grid-cols-2">
                <section className="admin-card p-5">
                  <h3 className="text-sm font-bold text-zinc-900">Daily revenue</h3>
                  <p className="mt-1 text-xs text-zinc-500">
                    {appliedFrom} → {appliedTo}
                  </p>
                  <DailyBarChart
                    data={revenueSeries}
                    valueKey="revenue"
                    formatValue={formatPrice}
                  />
                </section>
                <section className="admin-card p-5">
                  <h3 className="text-sm font-bold text-zinc-900">Orders trend</h3>
                  <p className="mt-1 text-xs text-zinc-500">
                    {appliedFrom} → {appliedTo}
                  </p>
                  <DailyBarChart
                    data={ordersSeries}
                    valueKey="orders"
                    formatValue={(n) => String(n)}
                  />
                </section>
              </div>
              </AdminDashboardSection>
            </AdminDashboardSectionShell>
          </div>
        );

      case "top_products_activity":
        return (
          <div key={section} className="space-y-4">
            {header}
            <AdminDashboardSectionShell
              collapsed={collapsed}
              onToggleCollapsed={toggle}
              error={sectionError(data, "top_products") ?? sectionError(data, "recent_activity")}
            >
              <AdminDashboardSection title="Top products & activity">
              <div className="grid gap-6 xl:grid-cols-3">
                <section className="admin-card overflow-hidden xl:col-span-2">
                  <div className="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
                    <h2 className="text-sm font-bold text-zinc-900">Top products</h2>
                    <Link
                      href="/admin/products"
                      className="text-xs font-semibold text-[#8b6914] hover:underline"
                    >
                      Catalog
                    </Link>
                  </div>
                  {!data.top_products?.length ? (
                    <p className="px-5 py-10 text-center text-sm text-zinc-500">
                      No product sales in this range.
                    </p>
                  ) : (
                    <AdminResponsiveTable
                      mobileCards={
                        <div className="divide-y divide-zinc-100 p-3">
                          {data.top_products.map((product) => (
                            <article
                              key={`${product.product_id ?? product.name}-mobile`}
                              className="admin-table-card !border-0 !p-0 !shadow-none first:pt-0 last:pb-0"
                            >
                              <p className="text-sm font-semibold text-zinc-900">{product.name}</p>
                              <div className="mt-2 grid gap-2">
                                <div className="admin-table-card-row">
                                  <span className="admin-table-card-label">Qty</span>
                                  <span className="admin-table-card-value">{product.quantity}</span>
                                </div>
                                <div className="admin-table-card-row">
                                  <span className="admin-table-card-label">Revenue</span>
                                  <span className="admin-table-card-value">
                                    {formatPrice(product.revenue)}
                                  </span>
                                </div>
                              </div>
                            </article>
                          ))}
                        </div>
                      }
                    >
                      <table className="w-full min-w-[480px] text-left text-sm">
                        <thead>
                          <tr className="border-b border-zinc-100 bg-zinc-50/80">
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                              Product
                            </th>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                              Qty
                            </th>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                              Revenue
                            </th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100">
                          {data.top_products.map((product) => (
                            <tr key={`${product.product_id ?? product.name}`} className="hover:bg-zinc-50/80">
                              <td className="px-4 py-3 font-medium text-zinc-900">{product.name}</td>
                              <td className="px-4 py-3 text-zinc-700">{product.quantity}</td>
                              <td className="px-4 py-3 font-semibold text-zinc-900">
                                {formatPrice(product.revenue)}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </AdminResponsiveTable>
                  )}
                </section>

                <section className="admin-card overflow-hidden">
                  <div className="border-b border-zinc-200 px-5 py-4">
                    <h2 className="text-sm font-bold text-zinc-900">Recent activity</h2>
                  </div>
                  {!data.recent_activity?.length ? (
                    <p className="px-5 py-10 text-center text-sm text-zinc-500">No recent activity.</p>
                  ) : (
                    <ul className="divide-y divide-zinc-100">
                      {data.recent_activity.map((item) => (
                        <li key={item.id} className="px-4 py-3">
                          <p className="text-sm font-medium text-zinc-900">
                            {item.description || item.event_type || "Activity"}
                          </p>
                          <p className="mt-0.5 text-xs text-zinc-500">
                            {[item.event_type, item.actor_type, formatWhen(item.created_at)]
                              .filter(Boolean)
                              .join(" · ")}
                          </p>
                        </li>
                      ))}
                    </ul>
                  )}
                </section>
              </div>
              </AdminDashboardSection>
            </AdminDashboardSectionShell>
          </div>
        );

      case "quick_exports":
        return (
          <div key={section} className="space-y-4">
            {header}
            <AdminDashboardSectionShell collapsed={collapsed} onToggleCollapsed={toggle}>
              <AdminDashboardSection title="Quick exports">
              <div className="admin-card p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="text-sm text-zinc-600">
                    Download CSV or XLSX for the selected date range.
                  </p>
                  <Link href="/admin/reports" className="text-xs font-semibold text-[#8b6914] hover:underline">
                    Open reports panel
                  </Link>
                  <Link href="/admin/alerts" className="text-xs font-semibold text-[#8b6914] hover:underline">
                    Alert center
                  </Link>
                </div>
                <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                  {adminReportTypes.map((type) => (
                    <div
                      key={type}
                      className="rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3"
                    >
                      <p className="text-sm font-semibold text-zinc-900">
                        {adminReportTypeLabels[type]}
                      </p>
                      <div className="mt-2 flex gap-2">
                        <button
                          type="button"
                          disabled={exporting !== null}
                          onClick={() => onExport(type, "csv")}
                          className="admin-touch-target rounded-lg bg-zinc-950 px-3 py-2 text-[11px] font-bold text-[#e8c547] disabled:opacity-50"
                        >
                          {exporting === `${type}-csv` ? "…" : "CSV"}
                        </button>
                        <button
                          type="button"
                          disabled={exporting !== null}
                          onClick={() => onExport(type, "xlsx")}
                          className="admin-touch-target rounded-lg border border-zinc-300 bg-white px-3 py-2 text-[11px] font-bold text-zinc-700 disabled:opacity-50"
                        >
                          {exporting === `${type}-xlsx` ? "…" : "XLSX"}
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              </AdminDashboardSection>
            </AdminDashboardSectionShell>
          </div>
        );

      default:
        return null;
    }
  };

  return <div className="space-y-8">{sectionOrder.map((section) => renderSection(section))}</div>;
}
