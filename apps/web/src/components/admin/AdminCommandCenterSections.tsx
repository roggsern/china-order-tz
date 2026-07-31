import Link from "next/link";
import { AdminStatCard } from "@/components/admin/AdminStatCard";
import { formatPrice } from "@/lib/catalog/utils";
import type {
  AdminCommandCenterAttentionItem,
  AdminCommandCenterChinaPipeline,
  AdminCommandCenterOperations,
  AdminCommandCenterOverview,
  AdminCommandCenterTzLocalPipeline,
  AdminStorefrontConversionMetrics,
  AdminStorefrontTrafficMetrics,
  AdminGrowthIntelligenceMetrics,
} from "@/lib/api/admin-reporting";
import {
  conversionCardValue,
  formatConversionRate,
  funnelStageValue,
  funnelStageWidth,
  STOREFRONT_CONVERSION_CARDS,
  STOREFRONT_FUNNEL_STAGES,
  storefrontConversionEmptyMessage,
} from "@/lib/admin/storefront-conversion";
import {
  formatGrowthHealthStatus,
  formatGrowthPercent,
  formatGrowthPoints,
  growthHealthAccent,
  growthIntelligenceEmptyMessage,
  growthSeverityBadgeClass,
  partitionGrowthAlerts,
} from "@/lib/admin/growth-intelligence";
import {
  formatStorefrontGrowthPercent,
  storefrontGrowthAccent,
  storefrontTrafficEmptyMessage,
} from "@/lib/admin/storefront-traffic";
import type {
  ChannelSummaryMetrics,
  FulfilmentPipelineMetrics,
  TodayOverviewMetrics,
} from "@/lib/admin/dashboard-command-center";

export function AdminDashboardSection({
  title,
  description,
  children,
  className = "",
}: {
  title: string;
  description?: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <section className={className}>
      <div className="flex flex-wrap items-end justify-between gap-2">
        <div>
          <h2 className="admin-dashboard-section-title">{title}</h2>
          {description ? <p className="mt-1 text-sm text-zinc-500">{description}</p> : null}
        </div>
      </div>
      <div className="mt-3 sm:mt-4">{children}</div>
    </section>
  );
}

export function AdminTodayOverviewSection({ metrics }: { metrics: TodayOverviewMetrics }) {
  return (
    <AdminDashboardSection
      title="Today overview"
      description="Start here — what needs attention right now."
    >
      <div className="admin-dashboard-grid-4">
        <AdminStatCard
          label="Today's orders"
          value={metrics.todaysOrders}
          href="/admin/orders"
          variant="dark"
          livePulse
        />
        <AdminStatCard
          label="Paid today"
          value={metrics.paidToday}
          accent="text-emerald-600"
          variant="gold"
        />
        <AdminStatCard
          label="Pending payment"
          value={metrics.pendingPaymentToday}
          accent="text-amber-700"
        />
        <AdminStatCard
          label="Action required"
          value={metrics.actionRequired}
          accent="text-red-600"
          href="/admin/fulfillments"
        />
      </div>
    </AdminDashboardSection>
  );
}

export function AdminBusinessOverviewSection({ metrics }: { metrics: AdminCommandCenterOverview }) {
  return (
    <AdminDashboardSection
      title="Business overview"
      description="Today's commercial pulse and customer activity."
    >
      <div className="admin-dashboard-grid-4">
        <AdminStatCard
          label="Today's orders"
          value={metrics.orders_today}
          href="/admin/orders"
          variant="dark"
          livePulse
        />
        <AdminStatCard
          label="Revenue today"
          value={formatPrice(metrics.revenue_today)}
          isText
          accent="text-emerald-600"
          variant="gold"
        />
        <AdminStatCard
          label="Paid orders today"
          value={metrics.paid_orders_today}
          accent="text-emerald-700"
        />
        <AdminStatCard
          label="Pending actions"
          value={metrics.pending_actions}
          accent="text-red-600"
          href="/admin/fulfillments"
        />
        <AdminStatCard
          label="Total customers"
          value={metrics.customers_total}
          href="/admin/customers"
        />
        <AdminStatCard
          label="New customers"
          value={metrics.new_customers}
          accent="text-[#8b6914]"
        />
      </div>
    </AdminDashboardSection>
  );
}

function PipelineMetric({ label, value, href }: { label: string; value: number; href?: string }) {
  const content = (
    <div className="admin-card px-4 py-4">
      <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">{label}</p>
      <p className="mt-2 text-2xl font-bold text-zinc-900">{value}</p>
    </div>
  );

  if (!href) {
    return content;
  }

  return (
    <Link href={href} className="block transition hover:opacity-90">
      {content}
    </Link>
  );
}

export function AdminOperationsTrafficSection({ metrics }: { metrics: AdminCommandCenterOperations }) {
  return (
    <AdminDashboardSection
      title="Operations traffic"
      description="Live fulfilment queue, warehouse workload, and shipment movement."
    >
      <div className="admin-dashboard-grid-4">
        <AdminStatCard
          label="Active fulfilments"
          value={metrics.fulfillment_queue.total}
          href="/admin/fulfillments"
        />
        <AdminStatCard
          label="China queue"
          value={metrics.fulfillment_queue.china}
          href="/admin/fulfillments?source=china"
        />
        <AdminStatCard
          label="TZ local queue"
          value={metrics.fulfillment_queue.local}
          href="/admin/fulfillments?source=local"
        />
        <AdminStatCard
          label="Open returns"
          value={metrics.open_returns}
          href="/admin/returns"
          accent="text-amber-700"
        />
      </div>
      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <article className="admin-card p-4">
          <h3 className="text-sm font-bold text-zinc-900">Warehouse workload</h3>
          <div className="mt-3 grid grid-cols-3 gap-2">
            <ChannelMetric label="Picking" value={metrics.warehouse.picking} />
            <ChannelMetric label="Packing" value={metrics.warehouse.packing} />
            <ChannelMetric label="Ready to ship" value={metrics.warehouse.ready_to_ship} />
          </div>
        </article>
        <article className="admin-card p-4">
          <h3 className="text-sm font-bold text-zinc-900">Shipment status</h3>
          <div className="mt-3 grid grid-cols-3 gap-2">
            <ChannelMetric label="Created" value={metrics.shipments.created} />
            <ChannelMetric label="In transit" value={metrics.shipments.in_transit} />
            <ChannelMetric label="Delivered" value={metrics.shipments.delivered} />
          </div>
        </article>
      </div>
    </AdminDashboardSection>
  );
}

const CHINA_PIPELINE_STAGES: Array<{
  key: keyof AdminCommandCenterChinaPipeline;
  label: string;
}> = [
  { key: "procurement", label: "Procurement" },
  { key: "qc_pending", label: "QC pending" },
  { key: "warehouse_packing", label: "Warehouse packing" },
  { key: "export_ready", label: "Export ready" },
  { key: "shipment_pending", label: "Shipment pending" },
  { key: "arrived_tanzania", label: "Arrived Tanzania" },
  { key: "awaiting_receiving_choice", label: "Awaiting choice" },
  { key: "handover_pending", label: "Handover pending" },
];

export function AdminChinaPipelineSection({ metrics }: { metrics: AdminCommandCenterChinaPipeline }) {
  return (
    <AdminDashboardSection
      title="China pipeline"
      description="Import fulfilment stages from procurement through Tanzania handover."
    >
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {CHINA_PIPELINE_STAGES.map((stage) => (
          <PipelineMetric
            key={stage.key}
            label={stage.label}
            value={metrics[stage.key]}
            href="/admin/fulfillments?source=china"
          />
        ))}
      </div>
    </AdminDashboardSection>
  );
}

const TZ_LOCAL_STAGES: Array<{
  key: keyof AdminCommandCenterTzLocalPipeline;
  label: string;
}> = [
  { key: "pending", label: "Pending" },
  { key: "processing", label: "Processing" },
  { key: "ready_for_shipping", label: "Ready for shipping" },
  { key: "shipped", label: "Shipped" },
  { key: "ready_for_completion", label: "Ready for completion" },
];

export function AdminTzLocalPipelineSection({ metrics }: { metrics: AdminCommandCenterTzLocalPipeline }) {
  return (
    <AdminDashboardSection
      title="TZ local pipeline"
      description="Buy From TZ warehouse and delivery flow."
    >
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {TZ_LOCAL_STAGES.map((stage) => (
          <PipelineMetric
            key={stage.key}
            label={stage.label}
            value={metrics[stage.key]}
            href="/admin/fulfillments?source=local"
          />
        ))}
      </div>
    </AdminDashboardSection>
  );
}

function attentionAccent(severity: AdminCommandCenterAttentionItem["severity"]): string {
  if (severity === "high") return "border-red-200 bg-red-50/80";
  if (severity === "medium") return "border-amber-200 bg-amber-50/80";
  return "border-zinc-200 bg-zinc-50/80";
}

export function AdminAttentionRequiredSection({
  items,
}: {
  items: AdminCommandCenterAttentionItem[];
}) {
  const actionable = items.filter((item) => item.count > 0);

  return (
    <AdminDashboardSection
      title="Attention required"
      description="Operational exceptions that need a human decision."
    >
      {actionable.length === 0 ? (
        <div className="admin-card px-5 py-8 text-center text-sm text-zinc-500">
          No outstanding attention items right now.
        </div>
      ) : (
        <div className="grid gap-3 lg:grid-cols-2">
          {actionable.map((item) => (
            <Link
              key={item.key}
              href={item.href}
              className={`rounded-xl border px-4 py-4 transition hover:opacity-90 ${attentionAccent(item.severity)}`}
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-zinc-900">{item.label}</p>
                  <p className="mt-1 text-xs text-zinc-500">Open queue</p>
                </div>
                <span className="text-2xl font-bold text-zinc-900">{item.count}</span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </AdminDashboardSection>
  );
}

function ChannelMetric({
  label,
  value,
  isPrice = false,
}: {
  label: string;
  value: number;
  isPrice?: boolean;
}) {
  return (
    <div className="rounded-xl border border-zinc-200 bg-zinc-50/70 px-3 py-3">
      <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">{label}</p>
      <p className="mt-1 text-lg font-bold text-zinc-900">
        {isPrice ? formatPrice(value) : value}
      </p>
    </div>
  );
}

export function AdminChannelSummaryCard({
  title,
  eyebrow,
  href,
  metrics,
  pendingLabel,
}: {
  title: string;
  eyebrow: string;
  href: string;
  metrics: ChannelSummaryMetrics;
  pendingLabel: string;
}) {
  return (
    <article className="admin-card p-4 sm:p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            {eyebrow}
          </p>
          <h3 className="mt-1 text-base font-bold text-zinc-900">{title}</h3>
        </div>
        <Link href={href} className="text-xs font-semibold text-[#8b6914] hover:underline">
          Open
        </Link>
      </div>
      <div className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
        <ChannelMetric label="Orders today" value={metrics.ordersToday} />
        <ChannelMetric label="Revenue today" value={metrics.revenue} isPrice />
        <ChannelMetric label={pendingLabel} value={metrics.pendingFulfilment} />
      </div>
    </article>
  );
}

const PIPELINE_STAGES: Array<{
  key: keyof FulfilmentPipelineMetrics;
  label: string;
}> = [
  { key: "paid", label: "Paid" },
  { key: "awaitingPurchase", label: "Awaiting purchase" },
  { key: "warehouse", label: "Warehouse" },
  { key: "shipping", label: "Shipping" },
  { key: "delivered", label: "Delivered" },
];

export function AdminFulfilmentPipelineSection({
  metrics,
}: {
  metrics: FulfilmentPipelineMetrics;
}) {
  return (
    <AdminDashboardSection
      title="Fulfilment pipeline"
      description="Operational flow from payment through delivery."
    >
      <div className="admin-fulfilment-pipeline">
        {PIPELINE_STAGES.map((stage, index) => (
          <div key={stage.key} className="admin-fulfilment-pipeline-stage">
            <div className="admin-card px-4 py-4">
              <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
                {stage.label}
              </p>
              <p className="mt-2 text-2xl font-bold text-zinc-900">{metrics[stage.key]}</p>
            </div>
            {index < PIPELINE_STAGES.length - 1 ? (
              <span className="admin-fulfilment-pipeline-arrow hidden lg:inline" aria-hidden>
                →
              </span>
            ) : null}
          </div>
        ))}
      </div>
    </AdminDashboardSection>
  );
}

export function AdminStorefrontTrafficSection({
  metrics,
}: {
  metrics: AdminStorefrontTrafficMetrics;
}) {
  const emptyMessage = storefrontTrafficEmptyMessage(metrics);

  return (
    <AdminDashboardSection
      title="Storefront traffic"
      description={`Visitor activity for ${metrics.reference_date}. Top lists use the selected reporting range.`}
    >
      <div className="admin-dashboard-grid-4">
        <AdminStatCard label="Visitors today" value={metrics.visitors_today} variant="dark" livePulse />
        <AdminStatCard label="Sessions" value={metrics.sessions_today} />
        <AdminStatCard label="New visitors" value={metrics.new_visitors} accent="text-emerald-600" />
        <AdminStatCard
          label="Returning visitors"
          value={metrics.returning_visitors}
          accent="text-[#8b6914]"
        />
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        <div className="rounded-xl border border-zinc-200 bg-white px-4 py-3">
          <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
            Visitor growth vs previous day
          </p>
          <p className={`mt-2 text-2xl font-bold ${storefrontGrowthAccent(metrics.growth.visitors_change_percent)}`}>
            {formatStorefrontGrowthPercent(metrics.growth.visitors_change_percent)}
          </p>
          <p className="mt-1 text-sm text-zinc-500">
            {metrics.growth.visitors_change >= 0 ? "+" : ""}
            {metrics.growth.visitors_change} visitors
          </p>
        </div>
        <div className="rounded-xl border border-zinc-200 bg-white px-4 py-3">
          <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-zinc-500">
            Session growth vs previous day
          </p>
          <p className={`mt-2 text-2xl font-bold ${storefrontGrowthAccent(metrics.growth.sessions_change_percent)}`}>
            {formatStorefrontGrowthPercent(metrics.growth.sessions_change_percent)}
          </p>
          <p className="mt-1 text-sm text-zinc-500">
            {metrics.growth.sessions_change >= 0 ? "+" : ""}
            {metrics.growth.sessions_change} sessions
          </p>
        </div>
      </div>

      {emptyMessage ? (
        <p className="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-sm text-zinc-500">
          {emptyMessage}
        </p>
      ) : (
        <div className="mt-4 grid gap-4 lg:grid-cols-3">
          <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <h3 className="text-sm font-semibold text-zinc-900">Top pages</h3>
            {metrics.top_pages.length === 0 ? (
              <p className="mt-3 text-sm text-zinc-500">No page views in this range.</p>
            ) : (
              <ul className="mt-3 space-y-2">
                {metrics.top_pages.map((row) => (
                  <li key={row.path} className="flex items-center justify-between gap-3 text-sm">
                    <span className="truncate font-mono text-zinc-700">{row.path}</span>
                    <span className="font-semibold text-zinc-900">{row.views}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <h3 className="text-sm font-semibold text-zinc-900">Top products</h3>
            {metrics.top_products.length === 0 ? (
              <p className="mt-3 text-sm text-zinc-500">No product views in this range.</p>
            ) : (
              <ul className="mt-3 space-y-2">
                {metrics.top_products.map((row) => (
                  <li key={row.product_id} className="flex items-center justify-between gap-3 text-sm">
                    <span className="truncate text-zinc-700">{row.name}</span>
                    <span className="font-semibold text-zinc-900">{row.views}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <h3 className="text-sm font-semibold text-zinc-900">Top searches</h3>
            {metrics.top_searches.length === 0 ? (
              <p className="mt-3 text-sm text-zinc-500">No searches in this range.</p>
            ) : (
              <ul className="mt-3 space-y-2">
                {metrics.top_searches.map((row) => (
                  <li key={row.query} className="flex items-center justify-between gap-3 text-sm">
                    <span className="truncate text-zinc-700">{row.query}</span>
                    <span className="font-semibold text-zinc-900">{row.count}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </AdminDashboardSection>
  );
}

export function AdminStorefrontConversionSection({
  metrics,
}: {
  metrics: AdminStorefrontConversionMetrics;
}) {
  const emptyMessage = storefrontConversionEmptyMessage(metrics);

  return (
    <AdminDashboardSection
      title="Storefront conversion"
      description="Visitor funnel and product conversion for the selected reporting range."
    >
      {emptyMessage ? (
        <p className="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-sm text-zinc-500">
          {emptyMessage}
        </p>
      ) : (
        <>
          <div className="space-y-3">
            {STOREFRONT_FUNNEL_STAGES.map((stage) => {
              const value = funnelStageValue(metrics, stage.key);
              const width = funnelStageWidth(metrics, stage.key);

              return (
                <div key={stage.key} className="rounded-xl border border-zinc-200 bg-white px-4 py-3">
                  <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="font-medium text-zinc-700">{stage.label}</span>
                    <span className="font-bold text-zinc-900">{value}</span>
                  </div>
                  <div className="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100">
                    <div
                      className="h-full rounded-full bg-[#c9a227] transition-all"
                      style={{ width: `${width}%` }}
                    />
                  </div>
                </div>
              );
            })}
          </div>

          <div className="mt-4 admin-dashboard-grid-4">
            {STOREFRONT_CONVERSION_CARDS.map((card) => (
              <AdminStatCard
                key={card.key}
                label={card.label}
                value={formatConversionRate(conversionCardValue(metrics, card.key))}
                isText
                accent="text-[#8b6914]"
              />
            ))}
          </div>

          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <div className="rounded-xl border border-zinc-200 bg-white p-4">
              <h3 className="text-sm font-semibold text-zinc-900">First-touch entry pages</h3>
              {metrics.attribution.first_touch_pages.length === 0 ? (
                <p className="mt-3 text-sm text-zinc-500">No attributed purchase journeys yet.</p>
              ) : (
                <ul className="mt-3 space-y-2">
                  {metrics.attribution.first_touch_pages.map((row) => (
                    <li key={row.path} className="flex items-center justify-between gap-3 text-sm">
                      <span className="truncate font-mono text-zinc-700">{row.path}</span>
                      <span className="font-semibold text-zinc-900">
                        {row.orders} orders · {row.visitors} visitors
                      </span>
                    </li>
                  ))}
                </ul>
              )}
              <p className="mt-3 text-xs text-zinc-500">
                {metrics.attribution.orders_with_journey} orders linked to visitor journeys ·{" "}
                {metrics.attribution.attributed_buyers} attributed buyers
              </p>
            </div>

            <div className="rounded-xl border border-zinc-200 bg-white p-4">
              <h3 className="text-sm font-semibold text-zinc-900">Product conversion insights</h3>
              {metrics.product_insights.length === 0 ? (
                <p className="mt-3 text-sm text-zinc-500">No product conversion data in this range.</p>
              ) : (
                <ul className="mt-3 space-y-3">
                  {metrics.product_insights.map((row) => (
                    <li key={row.product_id} className="rounded-lg border border-zinc-100 px-3 py-2">
                      <div className="flex items-center justify-between gap-3">
                        <span className="truncate text-sm font-medium text-zinc-800">{row.name}</span>
                        <span className="text-sm font-bold text-[#8b6914]">
                          {formatConversionRate(row.conversion_rate)}
                        </span>
                      </div>
                      <p className="mt-1 text-xs text-zinc-500">
                        {row.views} views · {row.cart_additions} cart · {row.orders} orders
                      </p>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </>
      )}
    </AdminDashboardSection>
  );
}

export function AdminGrowthIntelligenceSection({
  metrics,
}: {
  metrics: AdminGrowthIntelligenceMetrics;
}) {
  const emptyMessage = growthIntelligenceEmptyMessage(metrics);
  const { warnings, opportunities } = partitionGrowthAlerts(metrics);

  return (
    <AdminDashboardSection
      title="Growth intelligence"
      description="Rule-based business health, alerts, and opportunities from storefront analytics."
    >
      {emptyMessage ? (
        <p className="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-sm text-zinc-500">
          {emptyMessage}
        </p>
      ) : (
        <>
          <div className="grid gap-4 lg:grid-cols-4">
            <AdminStatCard
              label="Business health"
              value={formatGrowthHealthStatus(metrics.health_status)}
              isText
              accent={growthHealthAccent(metrics.health_status)}
              variant="dark"
            />
            <AdminStatCard
              label="Visitor growth"
              value={formatGrowthPercent(metrics.growth_comparisons.visitors_change_percent)}
              isText
              accent="text-[#8b6914]"
            />
            <AdminStatCard
              label="Conversion change"
              value={formatGrowthPoints(metrics.growth_comparisons.conversion_change_points)}
              isText
            />
            <AdminStatCard
              label="Visitor → purchase"
              value={`${metrics.health_summary.visitor_to_purchase.toFixed(1)}%`}
              isText
              accent="text-emerald-600"
            />
          </div>

          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <div className="rounded-xl border border-zinc-200 bg-white p-4">
              <h3 className="text-sm font-semibold text-zinc-900">Warnings</h3>
              {warnings.length === 0 ? (
                <p className="mt-3 text-sm text-zinc-500">No active warnings for this period.</p>
              ) : (
                <ul className="mt-3 space-y-3">
                  {warnings.map((alert) => (
                    <li key={`${alert.type}-${alert.title}`} className="rounded-lg border border-zinc-100 px-3 py-3">
                      <div className="flex items-start justify-between gap-3">
                        <p className="text-sm font-semibold text-zinc-900">{alert.title}</p>
                        <span className={`rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${growthSeverityBadgeClass(alert.severity)}`}>
                          {alert.severity}
                        </span>
                      </div>
                      <p className="mt-2 text-sm text-zinc-600">{alert.message}</p>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <div className="rounded-xl border border-zinc-200 bg-white p-4">
              <h3 className="text-sm font-semibold text-zinc-900">Opportunities</h3>
              {opportunities.length === 0 ? (
                <p className="mt-3 text-sm text-zinc-500">No opportunities surfaced for this period.</p>
              ) : (
                <ul className="mt-3 space-y-3">
                  {opportunities.map((alert) => (
                    <li key={`${alert.type}-${alert.title}`} className="rounded-lg border border-zinc-100 px-3 py-3">
                      <div className="flex items-start justify-between gap-3">
                        <p className="text-sm font-semibold text-zinc-900">{alert.title}</p>
                        <span className={`rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${growthSeverityBadgeClass(alert.severity)}`}>
                          {alert.severity}
                        </span>
                      </div>
                      <p className="mt-2 text-sm text-zinc-600">{alert.message}</p>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>

          <p className="mt-4 text-xs text-zinc-500">
            {metrics.health_summary.warning_count} warnings · {metrics.health_summary.opportunity_count} opportunities ·{" "}
            {metrics.health_summary.high_severity_count} high severity
          </p>
        </>
      )}
    </AdminDashboardSection>
  );
}
