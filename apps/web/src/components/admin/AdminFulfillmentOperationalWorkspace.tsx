"use client";

import type { ReactNode } from "react";
import { AdminFulfillmentAssignmentControl } from "@/components/admin/AdminFulfillmentAssignmentControl";
import { AdminFulfillmentNextActionsPanel } from "@/components/admin/AdminFulfillmentNextActionsPanel";
import { hasAdminPermission } from "@/lib/api/admin-me";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import Link from "next/link";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import {
  buildFulfillmentTimelineSteps,
  formatFulfillmentAge,
  FULFILLMENT_STATUS_STYLES,
  isChinaImportStrategy,
  resolveCustomerAgentDeliveryStageLabel,
  resolveCollectionPreferenceLabel,
  resolveFulfillmentJourneyLabel,
  resolveOperationalHealth,
  resolvePaymentStatusLabel,
  resolveRequiredAction,
  resolveAdminCustomerReceivingChoiceLabel,
  resolveAdminFulfillmentPresentationStatus,
  resolveAdminShipmentPresentationStatus,
  type FulfillmentOperationalModel,
} from "@/lib/admin/fulfillment-operational";
import { buildCustomerProgressDisplayTimeline } from "@/lib/order/customer-progress";

const STATUS_STYLES = FULFILLMENT_STATUS_STYLES;

function formatTimestamp(value?: string | null): string {
  if (!value) {
    return "—";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "—";
  }
  return date.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function SectionCard({
  title,
  description,
  children,
}: {
  title: string;
  description?: string;
  children: ReactNode;
}) {
  return (
    <section className="admin-card p-4 sm:p-5">
      <div>
        <h2 className="text-sm font-bold text-zinc-900">{title}</h2>
        {description ? <p className="mt-1 text-xs text-zinc-500">{description}</p> : null}
      </div>
      <div className="mt-4">{children}</div>
    </section>
  );
}

function MetricCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-zinc-100 bg-zinc-50/60 px-3 py-3">
      <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">{label}</p>
      <p className="mt-1 text-sm font-semibold text-zinc-900">{value}</p>
    </div>
  );
}

function ChinaWorkflowCards({ model }: { model: FulfillmentOperationalModel }) {
  const china = model.china;
  if (!china) {
    return null;
  }

  const procurement = china.procurement ?? [];
  const hasProcurement = procurement.length > 0;
  const hasStage = Boolean(china.stage_label || china.stage);
  const hasQc = Boolean(china.qc_status_label || china.qc_status);
  const hasExport = Boolean(china.export_readiness || china.export_ready_at);
  const hasWarehouse = Boolean(model.warehouse);

  if (!hasProcurement && !hasStage && !hasQc && !hasExport && !hasWarehouse) {
    return null;
  }

  return (
    <SectionCard
      title="China import workflow"
      description="Procurement, warehouse, QC, and export readiness for imported orders."
    >
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {hasProcurement ? (
          <div className="rounded-xl border border-zinc-100 bg-white p-4">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
              Procurement
            </p>
            <ul className="mt-3 space-y-2">
              {procurement.map((row) => (
                <li key={row.purchase_number ?? row.status} className="rounded-lg bg-zinc-50 px-3 py-2">
                  <p className="text-sm font-medium text-zinc-900">
                    {row.purchase_number ?? "Purchase order"}
                  </p>
                  <p className="mt-0.5 text-xs text-zinc-600">
                    {row.status_label ?? row.status ?? "—"}
                    {row.supplier_response ? ` · Supplier: ${row.supplier_response}` : ""}
                  </p>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        {hasStage ? (
          <MetricCard
            label="Workflow stage"
            value={china.stage_label ?? china.stage?.replaceAll("_", " ") ?? "—"}
          />
        ) : null}

        {hasQc ? (
          <MetricCard
            label="QC status"
            value={china.qc_status_label ?? china.qc_status?.replaceAll("_", " ") ?? "—"}
          />
        ) : null}

        {hasExport ? (
          <MetricCard
            label="Export readiness"
            value={
              china.export_readiness?.replaceAll("_", " ") ??
              (china.export_ready_at ? "Export ready" : "—")
            }
          />
        ) : null}

        {hasWarehouse ? (
          <MetricCard
            label="Warehouse"
            value={model.warehouse?.status_label ?? model.warehouse?.status ?? "—"}
          />
        ) : null}

        {china.export_ready_at ? (
          <MetricCard label="Export ready at" value={formatTimestamp(china.export_ready_at)} />
        ) : null}
      </div>
    </SectionCard>
  );
}

function LocalWorkflowCards({ model }: { model: FulfillmentOperationalModel }) {
  const source = model.order?.source ?? model.order?.journey ?? "—";
  const collectionPreference = resolveCollectionPreferenceLabel(model.order?.delivery_type);

  return (
    <SectionCard
      title="Buy From TZ operations"
      description="Local store fulfilment, warehouse preparation, and manual collection or delivery arrangement."
    >
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <MetricCard label="Order source" value={source.replaceAll("_", " ")} />
        <MetricCard
          label="Warehouse status"
          value={model.warehouse?.status_label ?? model.warehouse?.status ?? "Not started"}
        />
        <MetricCard label="Collection preference" value={collectionPreference} />
        {model.warehouse?.job_number ? (
          <MetricCard label="Warehouse job" value={model.warehouse.job_number} />
        ) : null}
        {model.shipment?.shipment_number ? (
          <MetricCard label="Shipment" value={model.shipment.shipment_number} />
        ) : null}
      </div>
    </SectionCard>
  );
}

function CustomerAgentDeliveryCard({ model }: { model: FulfillmentOperationalModel }) {
  const agent = model.customer_agent;
  if (!agent) {
    return null;
  }

  const currentStage = resolveCustomerAgentDeliveryStageLabel(model);

  return (
    <SectionCard
      title="Customer agent delivery"
      description="Seller fulfilment to the customer's nominated receiving agent."
    >
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <MetricCard
          label="Delivery method"
          value={agent.delivery_method_label ?? "Customer Agent Delivery"}
        />
        <MetricCard label="Agent" value={agent.agent_name ?? "—"} />
        <MetricCard
          label="Phone"
          value={agent.agent_phone ?? agent.agent_contact ?? "—"}
        />
        <MetricCard label="Current stage" value={currentStage} />
        {agent.agent_company ? (
          <MetricCard label="Agent company" value={agent.agent_company} />
        ) : null}
        {agent.agent_email ? <MetricCard label="Agent email" value={agent.agent_email} /> : null}
        {agent.handover_completed_at ? (
          <MetricCard label="Delivered at" value={formatTimestamp(agent.handover_completed_at)} />
        ) : null}
      </div>
    </SectionCard>
  );
}

interface AdminFulfillmentOperationalWorkspaceProps {
  model: FulfillmentOperationalModel;
  onRefresh: () => Promise<void>;
}

export function AdminFulfillmentOperationalWorkspace({
  model,
  onRefresh,
}: AdminFulfillmentOperationalWorkspaceProps) {
  const { permissions } = useAdminPermissions();
  const canManageAssignment = hasAdminPermission(permissions, "orders.fulfill");
  const product = model.order?.product;
  const timelineSteps = buildFulfillmentTimelineSteps(model);
  const showChina = isChinaImportStrategy(model.fulfillment.strategy);
  const journeyLabel = resolveFulfillmentJourneyLabel(
    model.fulfillment.strategy,
    model.order?.source ?? model.order?.journey,
  );
  const journeyKey = showChina ? "china" : "local";
  const statusLabel = resolveAdminFulfillmentPresentationStatus({
    fulfillmentStatus: model.fulfillment.status,
    fulfillmentStatusLabel: model.fulfillment.status_label,
    shipmentArrivedAt: model.shipment?.arrived_at,
    journey: journeyKey,
  });
  const receivingChoiceLabel = resolveAdminCustomerReceivingChoiceLabel(
    model.order?.last_mile_receiving_method,
  );
  const paymentLabel = resolvePaymentStatusLabel(model.order?.status);
  const ageLabel = formatFulfillmentAge(model.fulfillment.created_at);
  const health = resolveOperationalHealth(model);
  const requiredAction = resolveRequiredAction({
    status: model.fulfillment.status,
    strategy: model.fulfillment.strategy,
    source: model.order?.source ?? model.order?.journey,
    delivery_type: model.order?.delivery_type,
    last_mile_receiving_method: model.order?.last_mile_receiving_method,
    china: model.china,
    warehouse: model.warehouse,
    shipment: model.shipment,
  });
  const customerSteps = model.customer_progress
    ? buildCustomerProgressDisplayTimeline(model.customer_progress)
    : [];

  return (
    <div className="space-y-4">
      <header className="admin-card p-4 sm:p-5">
        <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
          <div className="min-w-0 space-y-3">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#8b6914]">
                Fulfilment workspace
              </p>
              <h1 className="mt-1 font-mono text-xl font-bold tracking-tight text-zinc-900 sm:text-2xl">
                {model.order?.order_number ?? "—"}
              </h1>
              <p className="mt-1 text-sm text-zinc-600">
                {model.order?.customer?.name ?? "Unknown customer"}
                {model.order?.customer?.email ? (
                  <span className="text-zinc-400"> · {model.order.customer.email}</span>
                ) : null}
              </p>
            </div>

            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
              <MetricCard label="Journey" value={journeyLabel} />
              <MetricCard label="Fulfilment status" value={statusLabel} />
              <MetricCard label="Payment status" value={paymentLabel} />
              <MetricCard label="Age" value={ageLabel} />
              {receivingChoiceLabel ? (
                <MetricCard label="Customer receiving" value={receivingChoiceLabel} />
              ) : null}
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <span
              className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ${
                STATUS_STYLES[model.fulfillment.status] ??
                "bg-zinc-50 text-zinc-700 ring-zinc-200"
              }`}
            >
              {statusLabel}
            </span>
          </div>
        </div>

        <div className="mt-4">
          <AdminFulfillmentAssignmentControl
            fulfillmentId={model.fulfillment.id}
            assignedTo={model.fulfillment.assigned_to}
            assignee={model.fulfillment.assignee ?? null}
            canManage={canManageAssignment}
          />
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <Link
            href="/admin/fulfillments"
            className="inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:border-zinc-300"
          >
            Back to queue
          </Link>
          {model.order?.id ? (
            <Link
              href={`/admin/orders/${encodeURIComponent(model.order.id)}`}
              className="inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:border-zinc-300"
            >
              View order
            </Link>
          ) : null}
        </div>
      </header>

      <section
        className={`admin-card p-4 sm:p-5 ${
          health.state === "needs_attention"
            ? "ring-1 ring-amber-200/80"
            : "ring-1 ring-emerald-100"
        }`}
      >
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
              Operational health
            </p>
            <p
              className={`mt-1 text-lg font-bold ${
                health.state === "needs_attention" ? "text-amber-900" : "text-emerald-800"
              }`}
            >
              {health.label}
            </p>
            <p className="mt-1 text-sm text-zinc-600">Next step: {requiredAction.label}</p>
          </div>
          <span
            className={`inline-flex self-start rounded-full px-3 py-1 text-xs font-semibold ring-1 ${
              health.state === "needs_attention"
                ? "bg-amber-50 text-amber-900 ring-amber-200"
                : "bg-emerald-50 text-emerald-800 ring-emerald-200"
            }`}
          >
            {health.state === "needs_attention" ? "Review required" : "On track"}
          </span>
        </div>
        {health.reasons.length > 0 ? (
          <ul className="mt-4 space-y-1.5 text-sm text-zinc-700">
            {health.reasons.map((reason) => (
              <li key={reason} className="flex gap-2">
                <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                <span>{reason}</span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-3 text-sm text-zinc-500">
            No blocking signals detected from current fulfilment, warehouse, and shipment state.
          </p>
        )}
      </section>

      <AdminFulfillmentNextActionsPanel model={model} onRefresh={onRefresh} />

      <SectionCard title="Product">
        {product ? (
          <div className="flex items-start gap-4">
            <div className="h-16 w-16 shrink-0 overflow-hidden rounded-xl ring-1 ring-zinc-200/80">
              <ProductImageDisplay
                src={product.image_url ?? undefined}
                className="h-full w-full"
                emojiClassName="text-2xl"
              />
            </div>
            <div className="min-w-0">
              <p className="text-base font-semibold text-zinc-900">{product.name}</p>
              {product.variant_label ? (
                <p className="mt-1 text-sm text-zinc-600">{product.variant_label}</p>
              ) : null}
              <p className="mt-1 text-sm font-medium text-zinc-700">Qty {product.quantity}</p>
              {(product.additional_item_count ?? 0) > 0 ? (
                <p className="mt-1 text-xs font-medium text-zinc-500">
                  +{product.additional_item_count} more item
                  {product.additional_item_count === 1 ? "" : "s"}
                </p>
              ) : null}
            </div>
          </div>
        ) : (
          <p className="text-sm text-zinc-500">No product details available.</p>
        )}
      </SectionCard>

      <SectionCard
        title="Operational timeline"
        description="Recorded fulfilment status history — not estimated."
      >
        <ol className="space-y-0">
          {timelineSteps.map((step, index) => (
            <li
              key={`${step.status}-${step.timestamp ?? index}`}
              className="relative flex gap-3 pb-6 last:pb-0"
            >
              {index < timelineSteps.length - 1 ? (
                <span className="absolute left-[11px] top-6 h-[calc(100%-12px)] w-px bg-zinc-200" />
              ) : null}
              <span
                className={`relative z-10 mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ${
                  index === timelineSteps.length - 1
                    ? "bg-zinc-900 text-white"
                    : "bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200"
                }`}
              >
                {index + 1}
              </span>
              <div className="min-w-0 flex-1 rounded-xl border border-zinc-100 bg-zinc-50/40 px-3 py-3">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                  <p className="text-sm font-semibold text-zinc-900">{step.label}</p>
                  <p className="text-xs tabular-nums text-zinc-500">
                    {formatTimestamp(step.timestamp)}
                  </p>
                </div>
                <p className="mt-1 text-xs text-zinc-600">
                  {step.sourceLabel}
                  {step.actorLabel ? ` · ${step.actorLabel}` : ""}
                </p>
                {step.notes ? (
                  <p className="mt-2 rounded-lg bg-white px-2.5 py-2 text-xs text-zinc-700 ring-1 ring-zinc-100">
                    {step.notes}
                  </p>
                ) : null}
              </div>
            </li>
          ))}
        </ol>
      </SectionCard>

      {showChina ? <ChinaWorkflowCards model={model} /> : <LocalWorkflowCards model={model} />}

      {model.customer_agent ? <CustomerAgentDeliveryCard model={model} /> : null}

      <div className="grid gap-4 xl:grid-cols-2">
        <SectionCard title="Warehouse">
          {model.warehouse ? (
            <div className="grid gap-3 sm:grid-cols-2">
              <MetricCard
                label="Job status"
                value={model.warehouse.status_label ?? model.warehouse.status}
              />
              <MetricCard label="Picker" value={model.warehouse.picker?.name ?? "—"} />
              <MetricCard label="Packer" value={model.warehouse.packer?.name ?? "—"} />
              <MetricCard label="Picked" value={formatTimestamp(model.warehouse.picked_at)} />
              <MetricCard label="Packed" value={formatTimestamp(model.warehouse.packed_at)} />
              <MetricCard label="Ready" value={formatTimestamp(model.warehouse.ready_at)} />
            </div>
          ) : (
            <p className="text-sm text-zinc-500">No warehouse job linked yet.</p>
          )}
        </SectionCard>

        <SectionCard title="Shipment">
          {model.shipment ? (
            <div className="grid gap-3 sm:grid-cols-2">
              <MetricCard
                label="Status"
                value={resolveAdminShipmentPresentationStatus(model.shipment)}
              />
              <MetricCard label="Carrier" value={model.shipment.carrier ?? "—"} />
              <MetricCard label="Tracking" value={model.shipment.tracking_number ?? "—"} />
              <MetricCard
                label="Transport"
                value={model.shipment.transport_mode?.replaceAll("_", " ") ?? "—"}
              />
              <MetricCard label="Booked" value={formatTimestamp(model.shipment.booked_at)} />
              <MetricCard label="Arrived" value={formatTimestamp(model.shipment.arrived_at)} />
              <MetricCard label="Delivered" value={formatTimestamp(model.shipment.delivered_at)} />
            </div>
          ) : (
            <p className="text-sm text-zinc-500">No shipment created yet.</p>
          )}
        </SectionCard>
      </div>

      <SectionCard
        title="Customer progress preview"
        description="Informational only — customers see this on their order tracking."
      >
        {model.customer_progress ? (
          <div>
            <p className="text-sm font-semibold text-zinc-800">
              Customer sees: {model.customer_progress.current_label}
            </p>
            <ul className="mt-3 space-y-2">
              {customerSteps.map((step) => (
                <li
                  key={step.key}
                  className={`rounded-lg border px-3 py-2 text-sm ${
                    step.state === "current"
                      ? "border-[#c9a227]/40 bg-amber-50/50 font-semibold text-zinc-900"
                      : step.state === "completed"
                        ? "border-emerald-100 bg-emerald-50/40 text-zinc-700"
                        : "border-zinc-100 bg-white text-zinc-500"
                  }`}
                >
                  {step.label}
                </li>
              ))}
            </ul>
          </div>
        ) : (
          <p className="text-sm text-zinc-500">Customer progress unavailable.</p>
        )}
      </SectionCard>
    </div>
  );
}
