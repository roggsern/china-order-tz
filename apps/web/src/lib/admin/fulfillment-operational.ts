import type { AdminFulfillment } from "@/lib/api/admin-fulfillments";
import type { CustomerOrderProgress } from "@/lib/order/customer-progress";

export type FulfillmentJourneyFilter = "all" | "china" | "local";
export type FulfillmentActionRequiredFilter =
  | "all"
  | "needs_purchase"
  | "needs_warehouse"
  | "needs_shipment"
  | "completed";

export type FulfillmentProductSummary = {
  name: string;
  variant_label?: string | null;
  quantity: number;
  image_url?: string | null;
  additional_item_count?: number;
};

export type FulfillmentStatusHistoryEntry = {
  from_status?: string | null;
  to_status: string;
  source?: string | null;
  changed_by?: string | null;
  changed_by_admin?: { id: string; name: string } | null;
  notes?: string | null;
  created_at?: string | null;
};

export type FulfillmentOperationalModel = {
  fulfillment: {
    id: string;
    status: string;
    status_label?: string | null;
    strategy: string;
    strategy_label?: string | null;
    assigned_to?: string | null;
    assignee?: { id: string; name: string; email?: string } | null;
    started_at?: string | null;
    completed_at?: string | null;
    notes?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
  };
  order: {
    id: string;
    order_number: string;
    status?: string;
    source?: string;
    journey?: string;
    customer?: {
      id: string;
      name: string;
      email: string;
      phone?: string | null;
    } | null;
    delivery_type?: string | null;
    last_mile_receiving_method?: string | null;
    product?: FulfillmentProductSummary | null;
  } | null;
  warehouse: {
    id: string;
    job_number?: string;
    status: string;
    status_label?: string | null;
    picker?: { id: string; name: string } | null;
    packer?: { id: string; name: string } | null;
    picked_at?: string | null;
    packed_at?: string | null;
    ready_at?: string | null;
  } | null;
  shipment: {
    id: string;
    shipment_number?: string;
    status: string;
    status_label?: string | null;
    carrier?: string | null;
    tracking_number?: string | null;
    transport_mode?: string | null;
    booked_at?: string | null;
    shipped_at?: string | null;
    arrived_at?: string | null;
    delivered_at?: string | null;
  } | null;
  china: {
    stage?: string | null;
    stage_label?: string | null;
    qc_status?: string | null;
    qc_status_label?: string | null;
    export_readiness?: string | null;
    export_ready_at?: string | null;
    procurement?: Array<{
      purchase_number?: string;
      status?: string;
      status_label?: string | null;
      supplier_response?: string | null;
    }>;
  } | null;
  customer_agent: {
    delivery_method_label?: string | null;
    agent_name?: string | null;
    agent_phone?: string | null;
    agent_contact?: string | null;
    agent_company?: string | null;
    agent_email?: string | null;
    pickup_reference?: string | null;
    authorization_status?: string | null;
    release_status?: string | null;
    pickup_status?: string | null;
    handover_completed_at?: string | null;
  } | null;
  customer_progress: CustomerOrderProgress | null;
  status_history: FulfillmentStatusHistoryEntry[];
};

export type FulfillmentQueueRow = {
  id: string;
  orderId: string;
  orderNumber: string;
  customerName: string;
  productName: string;
  productVariant?: string;
  productQuantity: number;
  productImageUrl?: string | null;
  additionalItemCount: number;
  journeyLabel: string;
  journeyKey: "china" | "local";
  currentStage: string;
  status: string;
  requiredAction: string;
  actionCategory: Exclude<FulfillmentActionRequiredFilter, "all">;
  ageLabel: string;
  ageMs: number;
  assignedLabel: string;
  strategy: string;
};

export type QueueSummaryKey =
  | "awaiting_purchase"
  | "warehouse_processing"
  | "ready_to_ship"
  | "in_transit"
  | "needs_attention";

export type QueueRowVisualIndicator = "normal" | "urgent" | "delayed" | "completed";

export type OperationalHealthState = "healthy" | "needs_attention";

export type QueueSummaryCard = {
  key: QueueSummaryKey;
  label: string;
  description: string;
  count: number;
};

const QUEUE_SUMMARY_DEFINITIONS: Record<
  QueueSummaryKey,
  { label: string; description: string }
> = {
  awaiting_purchase: {
    label: "Awaiting purchase",
    description: "China procurement or supplier action pending",
  },
  warehouse_processing: {
    label: "Warehouse processing",
    description: "Pick, pack, or warehouse prep in progress",
  },
  ready_to_ship: {
    label: "Ready to ship",
    description: "Packed and waiting for shipment booking",
  },
  in_transit: {
    label: "In transit",
    description: "Shipped and moving through logistics",
  },
  needs_attention: {
    label: "Needs attention",
    description: "Delayed or blocked operational steps",
  },
};

const DELAYED_AGE_MS = 48 * 60 * 60 * 1000;
const URGENT_AGE_MS = 24 * 60 * 60 * 1000;

const PAYMENT_STATUS_LABELS: Record<string, string> = {
  pending: "Pending payment",
  pending_payment: "Pending payment",
  paid: "Paid",
  confirmed: "Confirmed",
  processing: "Processing",
  shipped: "Shipped",
  delivered: "Delivered",
  completed: "Completed",
  cancelled: "Cancelled",
  refund_pending: "Refund pending",
  refunded: "Refunded",
};

const STATUS_LABELS: Record<string, string> = {
  pending: "Pending",
  processing: "Processing",
  ready_for_shipping: "Ready for shipping",
  shipped: "Shipped",
  delivered: "Delivered",
  cancelled: "Cancelled",
};

const HISTORY_SOURCE_LABELS: Record<string, string> = {
  admin: "Admin update",
  warehouse_sync: "Warehouse sync",
  shipment_reconciliation: "Delivery confirmed",
  shipment_dispatch: "Shipment dispatched",
  customer_agent: "Customer agent",
  order_cancel: "Order cancelled",
  system: "System",
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function parseFulfillmentOperationalModel(value: unknown): FulfillmentOperationalModel | null {
  if (!isRecord(value) || !isRecord(value.fulfillment)) {
    return null;
  }

  const fulfillment = value.fulfillment;
  const order = isRecord(value.order) ? value.order : null;
  const statusHistory = Array.isArray(value.status_history)
    ? value.status_history.filter(isRecord).map((entry) => ({
        from_status: entry.from_status != null ? String(entry.from_status) : null,
        to_status: String(entry.to_status ?? ""),
        source: entry.source != null ? String(entry.source) : null,
        changed_by: entry.changed_by != null ? String(entry.changed_by) : null,
        changed_by_admin:
          isRecord(entry.changed_by_admin) && entry.changed_by_admin.name
            ? {
                id: String(entry.changed_by_admin.id ?? ""),
                name: String(entry.changed_by_admin.name),
              }
            : null,
        notes: entry.notes != null ? String(entry.notes) : null,
        created_at: entry.created_at != null ? String(entry.created_at) : null,
      }))
    : [];

  return {
    fulfillment: {
      id: String(fulfillment.id ?? ""),
      status: String(fulfillment.status ?? ""),
      status_label: fulfillment.status_label != null ? String(fulfillment.status_label) : null,
      strategy: String(fulfillment.strategy ?? ""),
      strategy_label:
        fulfillment.strategy_label != null ? String(fulfillment.strategy_label) : null,
      assigned_to: fulfillment.assigned_to != null ? String(fulfillment.assigned_to) : null,
      assignee:
        isRecord(fulfillment.assignee) && fulfillment.assignee.name
          ? {
              id: String(fulfillment.assignee.id ?? ""),
              name: String(fulfillment.assignee.name),
              email:
                fulfillment.assignee.email != null ? String(fulfillment.assignee.email) : undefined,
            }
          : null,
      started_at: fulfillment.started_at != null ? String(fulfillment.started_at) : null,
      completed_at: fulfillment.completed_at != null ? String(fulfillment.completed_at) : null,
      notes: fulfillment.notes != null ? String(fulfillment.notes) : null,
      created_at: fulfillment.created_at != null ? String(fulfillment.created_at) : null,
      updated_at: fulfillment.updated_at != null ? String(fulfillment.updated_at) : null,
    },
    order: order
      ? {
          id: String(order.id ?? ""),
          order_number: String(order.order_number ?? ""),
          status: order.status != null ? String(order.status) : undefined,
          source: order.source != null ? String(order.source) : undefined,
          journey: order.journey != null ? String(order.journey) : undefined,
          customer:
            isRecord(order.customer) && order.customer.name
              ? {
                  id: String(order.customer.id ?? ""),
                  name: String(order.customer.name),
                  email: String(order.customer.email ?? ""),
                  phone: order.customer.phone != null ? String(order.customer.phone) : null,
                }
              : null,
          delivery_type: order.delivery_type != null ? String(order.delivery_type) : null,
          last_mile_receiving_method:
            order.last_mile_receiving_method != null
              ? String(order.last_mile_receiving_method)
              : null,
          product: parseProductSummary(order.product),
        }
      : null,
    warehouse: isRecord(value.warehouse)
      ? {
          id: String(value.warehouse.id ?? ""),
          job_number:
            value.warehouse.job_number != null ? String(value.warehouse.job_number) : undefined,
          status: String(value.warehouse.status ?? ""),
          status_label:
            value.warehouse.status_label != null ? String(value.warehouse.status_label) : null,
          picker:
            isRecord(value.warehouse.picker) && value.warehouse.picker.name
              ? {
                  id: String(value.warehouse.picker.id ?? ""),
                  name: String(value.warehouse.picker.name),
                }
              : null,
          packer:
            isRecord(value.warehouse.packer) && value.warehouse.packer.name
              ? {
                  id: String(value.warehouse.packer.id ?? ""),
                  name: String(value.warehouse.packer.name),
                }
              : null,
          picked_at:
            value.warehouse.picked_at != null ? String(value.warehouse.picked_at) : null,
          packed_at:
            value.warehouse.packed_at != null ? String(value.warehouse.packed_at) : null,
          ready_at: value.warehouse.ready_at != null ? String(value.warehouse.ready_at) : null,
        }
      : null,
    shipment: isRecord(value.shipment)
      ? {
          id: String(value.shipment.id ?? ""),
          shipment_number:
            value.shipment.shipment_number != null
              ? String(value.shipment.shipment_number)
              : undefined,
          status: String(value.shipment.status ?? ""),
          status_label:
            value.shipment.status_label != null ? String(value.shipment.status_label) : null,
          carrier: value.shipment.carrier != null ? String(value.shipment.carrier) : null,
          tracking_number:
            value.shipment.tracking_number != null
              ? String(value.shipment.tracking_number)
              : null,
          transport_mode:
            value.shipment.transport_mode != null ? String(value.shipment.transport_mode) : null,
          booked_at: value.shipment.booked_at != null ? String(value.shipment.booked_at) : null,
          shipped_at:
            value.shipment.shipped_at != null ? String(value.shipment.shipped_at) : null,
          arrived_at:
            value.shipment.arrived_at != null ? String(value.shipment.arrived_at) : null,
          delivered_at:
            value.shipment.delivered_at != null ? String(value.shipment.delivered_at) : null,
        }
      : null,
    china: isRecord(value.china)
      ? {
          stage: value.china.stage != null ? String(value.china.stage) : null,
          stage_label: value.china.stage_label != null ? String(value.china.stage_label) : null,
          qc_status: value.china.qc_status != null ? String(value.china.qc_status) : null,
          qc_status_label:
            value.china.qc_status_label != null ? String(value.china.qc_status_label) : null,
          export_readiness:
            value.china.export_readiness != null ? String(value.china.export_readiness) : null,
          export_ready_at:
            value.china.export_ready_at != null ? String(value.china.export_ready_at) : null,
          procurement: Array.isArray(value.china.procurement)
            ? value.china.procurement.filter(isRecord).map((row) => ({
                purchase_number:
                  row.purchase_number != null ? String(row.purchase_number) : undefined,
                status: row.status != null ? String(row.status) : undefined,
                status_label: row.status_label != null ? String(row.status_label) : null,
                supplier_response:
                  row.supplier_response != null ? String(row.supplier_response) : null,
              }))
            : [],
        }
      : null,
    customer_agent: isRecord(value.customer_agent)
      ? {
          delivery_method_label:
            value.customer_agent.delivery_method_label != null
              ? String(value.customer_agent.delivery_method_label)
              : null,
          agent_name:
            value.customer_agent.agent_name != null ? String(value.customer_agent.agent_name) : null,
          agent_phone:
            value.customer_agent.agent_phone != null ? String(value.customer_agent.agent_phone) : null,
          agent_contact:
            value.customer_agent.agent_contact != null
              ? String(value.customer_agent.agent_contact)
              : null,
          agent_company:
            value.customer_agent.agent_company != null
              ? String(value.customer_agent.agent_company)
              : null,
          agent_email:
            value.customer_agent.agent_email != null ? String(value.customer_agent.agent_email) : null,
          pickup_reference:
            value.customer_agent.pickup_reference != null
              ? String(value.customer_agent.pickup_reference)
              : null,
          authorization_status:
            value.customer_agent.authorization_status != null
              ? String(value.customer_agent.authorization_status)
              : null,
          release_status:
            value.customer_agent.release_status != null
              ? String(value.customer_agent.release_status)
              : null,
          pickup_status:
            value.customer_agent.pickup_status != null
              ? String(value.customer_agent.pickup_status)
              : null,
          handover_completed_at:
            value.customer_agent.handover_completed_at != null
              ? String(value.customer_agent.handover_completed_at)
              : null,
        }
      : null,
    customer_progress:
      isRecord(value.customer_progress) &&
      value.customer_progress.current_key &&
      value.customer_progress.current_label
        ? {
            current_key: String(value.customer_progress.current_key),
            current_label: String(value.customer_progress.current_label),
            steps: Array.isArray(value.customer_progress.steps)
              ? value.customer_progress.steps
                  .filter(isRecord)
                  .map((step) => ({
                    key: String(step.key ?? ""),
                    label: String(step.label ?? ""),
                    completed: Boolean(step.completed),
                  }))
                  .filter((step) => step.key.length > 0)
              : [],
          }
        : null,
    status_history: statusHistory.filter((entry) => entry.to_status.length > 0),
  };
}

function parseProductSummary(value: unknown): FulfillmentProductSummary | null {
  if (!isRecord(value) || !value.name) {
    return null;
  }

  return {
    name: String(value.name),
    variant_label: value.variant_label != null ? String(value.variant_label) : null,
    quantity: Number(value.quantity ?? 1),
    image_url: value.image_url != null ? String(value.image_url) : null,
    additional_item_count:
      value.additional_item_count != null ? Number(value.additional_item_count) : 0,
  };
}

export function resolveCustomerAgentDeliveryStageLabel(
  model: Pick<FulfillmentOperationalModel, "customer_progress" | "customer_agent">,
): string {
  const label = model.customer_progress?.current_label?.trim();
  if (label) {
    return label;
  }

  if (model.customer_agent?.handover_completed_at) {
    return "Delivered to your agent";
  }

  return "Preparing your order";
}

export function resolveFulfillmentJourneyKey(
  strategy: string,
  source?: string | null,
): "china" | "local" {
  const normalized = `${strategy} ${source ?? ""}`.toLowerCase();
  if (normalized.includes("china") || normalized.includes("import")) {
    return "china";
  }
  return "local";
}

export function resolveFulfillmentJourneyLabel(
  strategy: string,
  source?: string | null,
): string {
  return resolveFulfillmentJourneyKey(strategy, source) === "china"
    ? "Order from China"
    : "Buy From TZ";
}

export function resolveCollectionPreferenceLabel(deliveryType?: string | null): string {
  switch (deliveryType) {
    case "self_pickup":
      return "Self Pickup";
    case "negotiated_delivery":
      return "Delivery Arrangement";
    default:
      return deliveryType?.replaceAll("_", " ") ?? "—";
  }
}

export function resolveFulfillmentStatusLabel(
  status: string,
  statusLabel?: string | null,
  options?: { journey?: "china" | "local" },
): string {
  if (options?.journey === "local") {
    const localLabels: Record<string, string> = {
      pending: "Pending",
      processing: "Preparing",
      ready_for_shipping: "Order ready",
      shipped: "Order ready",
      delivered: "Completed",
      cancelled: "Cancelled",
    };
    if (localLabels[status]) {
      return localLabels[status];
    }
  }

  if (statusLabel?.trim()) {
    return statusLabel.trim();
  }
  return STATUS_LABELS[status] ?? status.replaceAll("_", " ");
}

export function resolveHistorySourceLabel(source?: string | null): string {
  if (!source) {
    return "System";
  }
  const normalized = source.trim().toLowerCase();
  return HISTORY_SOURCE_LABELS[normalized] ?? source.replaceAll("_", " ");
}

export function resolvePaymentStatusLabel(status?: string | null): string {
  if (!status?.trim()) {
    return "—";
  }
  const normalized = status.trim().toLowerCase();
  return PAYMENT_STATUS_LABELS[normalized] ?? status.replaceAll("_", " ");
}

export function resolveQueueRowVisualIndicator(row: FulfillmentQueueRow): QueueRowVisualIndicator {
  if (
    row.actionCategory === "completed" ||
    row.status === "delivered" ||
    row.status === "cancelled"
  ) {
    return "completed";
  }
  if (row.ageMs >= DELAYED_AGE_MS) {
    return "delayed";
  }
  if (row.ageMs >= URGENT_AGE_MS) {
    return "urgent";
  }
  return "normal";
}

export function computeQueueSummaryCards(rows: FulfillmentQueueRow[]): QueueSummaryCard[] {
  const counts: Record<QueueSummaryKey, number> = {
    awaiting_purchase: rows.filter((row) => row.actionCategory === "needs_purchase").length,
    warehouse_processing: rows.filter(
      (row) =>
        row.status === "processing" ||
        (row.actionCategory === "needs_warehouse" &&
          !["ready_for_shipping", "shipped"].includes(row.status)),
    ).length,
    ready_to_ship: rows.filter((row) => row.status === "ready_for_shipping").length,
    in_transit: rows.filter((row) => row.status === "shipped").length,
    needs_attention: rows.filter((row) => {
      const indicator = resolveQueueRowVisualIndicator(row);
      return indicator === "delayed" || indicator === "urgent";
    }).length,
  };

  return (Object.keys(QUEUE_SUMMARY_DEFINITIONS) as QueueSummaryKey[]).map((key) => ({
    key,
    label: QUEUE_SUMMARY_DEFINITIONS[key].label,
    description: QUEUE_SUMMARY_DEFINITIONS[key].description,
    count: counts[key],
  }));
}

export function resolveOperationalHealth(model: FulfillmentOperationalModel): {
  state: OperationalHealthState;
  label: string;
  reasons: string[];
} {
  const reasons: string[] = [];
  const { fulfillment, shipment, china, warehouse } = model;
  const status = fulfillment.status;

  if (status === "delivered" || status === "cancelled") {
    return { state: "healthy", label: "Complete", reasons: [] };
  }

  const ageMs = fulfillmentAgeMs(fulfillment.created_at);
  if (ageMs >= DELAYED_AGE_MS) {
    reasons.push(`Open for ${formatFulfillmentAge(fulfillment.created_at)} — review progress`);
  }

  const required = resolveRequiredAction({
    status,
    strategy: fulfillment.strategy,
    source: model.order?.source ?? model.order?.journey,
    delivery_type: model.order?.delivery_type,
    china: model.china,
    warehouse: model.warehouse,
    shipment: model.shipment,
  });

  const isLocal = resolveFulfillmentJourneyKey(fulfillment.strategy, model.order?.source ?? model.order?.journey) === "local";

  if (status === "ready_for_shipping" && !shipment) {
    if (isLocal) {
      reasons.push("Order is ready and awaiting customer completion.");
    } else {
      reasons.push("Ready to ship without a shipment record");
    }
  }

  if (status === "processing" && !warehouse) {
    reasons.push("Processing without a warehouse job");
  }

  if (isChinaImportStrategy(fulfillment.strategy) && china) {
    const qc = (china.qc_status ?? "").toLowerCase();
    if (qc === "failed") {
      reasons.push("QC marked as failed");
    }
    if (required.category === "needs_purchase" && status !== "pending") {
      reasons.push("China workflow awaiting next procurement step");
    }
  }

  if (required.category !== "completed" && ageMs >= URGENT_AGE_MS && reasons.length === 0) {
    reasons.push(`Action required: ${required.label}`);
  }

  return {
    state: reasons.length > 0 ? "needs_attention" : "healthy",
    label: reasons.length > 0 ? "Needs attention" : "Healthy",
    reasons,
  };
}

export function matchesQueueSearch(row: FulfillmentQueueRow, query: string): boolean {
  const normalized = query.trim().toLowerCase();
  if (!normalized) {
    return true;
  }

  const haystack = [
    row.orderNumber,
    row.customerName,
    row.productName,
    row.productVariant ?? "",
    row.requiredAction,
    row.assignedLabel,
  ]
    .join(" ")
    .toLowerCase();

  return haystack.includes(normalized);
}

export function formatFulfillmentAge(isoDate?: string | null, now = Date.now()): string {
  if (!isoDate) {
    return "—";
  }
  const created = Date.parse(isoDate);
  if (Number.isNaN(created)) {
    return "—";
  }
  const diffMs = Math.max(0, now - created);
  const minutes = Math.floor(diffMs / 60_000);
  if (minutes < 60) {
    return `${minutes}m`;
  }
  const hours = Math.floor(minutes / 60);
  if (hours < 48) {
    return `${hours}h`;
  }
  const days = Math.floor(hours / 24);
  return `${days}d`;
}

export function fulfillmentAgeMs(isoDate?: string | null, now = Date.now()): number {
  if (!isoDate) {
    return 0;
  }
  const created = Date.parse(isoDate);
  if (Number.isNaN(created)) {
    return 0;
  }
  return Math.max(0, now - created);
}

type RequiredActionInput = {
  status: string;
  strategy: string;
  source?: string | null;
  delivery_type?: string | null;
  last_mile_receiving_method?: string | null;
  china?: {
    stage?: string | null;
    qc_status?: string | null;
    procurement?: Array<{ status?: string | null }>;
  } | null;
  warehouse?: { status: string } | null;
  shipment?: { status: string; arrived_at?: string | null } | null;
};

function isCompanyShippingDeliveryType(deliveryType?: string | null): boolean {
  return (deliveryType ?? "").toLowerCase() === "company_shipping";
}

function buildRequiredActionContextFromListRow(row: AdminFulfillment): RequiredActionInput {
  const source = row.order?.source ?? row.order?.journey;

  return {
    status: row.status,
    strategy: row.strategy,
    source,
    delivery_type: row.order?.delivery_type,
    last_mile_receiving_method: row.order?.last_mile_receiving_method,
    china: row.china
      ? {
          stage: row.china.stage,
          qc_status: row.china.qc_status,
        }
      : undefined,
    warehouse: row.warehouse_status ? { status: row.warehouse_status } : undefined,
    shipment:
      row.shipment_status || row.shipment_arrived_at
        ? {
            status: row.shipment_status ?? "",
            arrived_at: row.shipment_arrived_at ?? null,
          }
        : undefined,
  };
}

export function resolveRequiredAction(input: RequiredActionInput): {
  label: string;
  category: Exclude<FulfillmentActionRequiredFilter, "all">;
} {
  const {
    status,
    strategy,
    source,
    delivery_type,
    last_mile_receiving_method,
    china,
    warehouse,
    shipment,
  } = input;
  const journey = resolveFulfillmentJourneyKey(strategy, source);

  if (status === "delivered" || status === "cancelled") {
    return { label: "No action required", category: "completed" };
  }

  if (journey === "china") {
    const openProcurement = china?.procurement?.some((row) => {
      const procurementStatus = (row.status ?? "").toLowerCase();
      return procurementStatus !== "received" && procurementStatus !== "completed";
    });

    if (
      (status === "pending" || openProcurement) &&
      (!china?.stage || ["awaiting_purchase", "procurement"].includes(china.stage))
    ) {
      return { label: "Purchase from supplier", category: "needs_purchase" };
    }
  } else if (status === "pending") {
    if (
      journey === "local" &&
      (delivery_type === "self_pickup" || delivery_type === "negotiated_delivery")
    ) {
      return { label: "Mark order ready", category: "needs_warehouse" };
    }
    return { label: "Start warehouse processing", category: "needs_warehouse" };
  }

  if (status === "processing") {
    const warehouseStatus = (warehouse?.status ?? "").toLowerCase();
    if (journey === "china" && china?.stage && !["export_ready", "shipped"].includes(china.stage)) {
      return { label: "Advance China workflow", category: "needs_purchase" };
    }
    if (
      journey === "local" &&
      (delivery_type === "self_pickup" || delivery_type === "negotiated_delivery") &&
      warehouseStatus !== "ready_to_ship"
    ) {
      return { label: "Mark order ready", category: "needs_warehouse" };
    }
    if (!warehouse || ["pending", "assigned", "picking"].includes(warehouseStatus)) {
      return { label: "Complete warehouse pick/pack", category: "needs_warehouse" };
    }
    return { label: "Finish warehouse preparation", category: "needs_warehouse" };
  }

  if (status === "ready_for_shipping") {
    if (journey === "local") {
      return { label: "Mark order completed", category: "needs_warehouse" };
    }

    if (!shipment) {
      return { label: "Create shipment", category: "needs_shipment" };
    }
    return { label: "Book or dispatch shipment", category: "needs_shipment" };
  }

  if (status === "shipped") {
    if (journey === "local") {
      return { label: "Complete order when fulfilled", category: "needs_warehouse" };
    }

    if (journey === "china" && isCompanyShippingDeliveryType(delivery_type) && shipment?.arrived_at) {
      const method = (last_mile_receiving_method ?? "").toLowerCase();
      if (!method) {
        return { label: "Await customer choice", category: "needs_shipment" };
      }
      if (method === "self_pickup") {
        return { label: "Await customer collection", category: "needs_shipment" };
      }
      if (method === "negotiated_delivery") {
        return { label: "Await delivery completion", category: "needs_shipment" };
      }
    }

    if (shipment?.status !== "delivered") {
      return { label: "Track until delivered", category: "needs_shipment" };
    }
    return { label: "Confirm delivery", category: "needs_shipment" };
  }

  return { label: "Review fulfilment", category: "needs_warehouse" };
}

export function mapAdminFulfillmentToQueueRow(
  row: AdminFulfillment,
  now = Date.now(),
): FulfillmentQueueRow {
  const product = row.order?.product;
  const source = row.order?.source ?? row.order?.journey;
  const journeyKey = resolveFulfillmentJourneyKey(row.strategy, source);
  const { label, category } = resolveRequiredAction(buildRequiredActionContextFromListRow(row));

  return {
    id: row.id,
    orderId: row.order_id,
    orderNumber: row.order?.order_number ?? "—",
    customerName: row.order?.customer?.name ?? "—",
    productName: product?.name ?? "—",
    productVariant: product?.variant_label ?? undefined,
    productQuantity: product?.quantity ?? 0,
    productImageUrl: product?.image_url,
    additionalItemCount: product?.additional_item_count ?? 0,
    journeyLabel: resolveFulfillmentJourneyLabel(row.strategy, source),
    journeyKey,
    currentStage: resolveFulfillmentStatusLabel(row.status, row.status_label),
    status: row.status,
    requiredAction: label,
    actionCategory: category,
    ageLabel: formatFulfillmentAge(row.created_at, now),
    ageMs: fulfillmentAgeMs(row.created_at, now),
    assignedLabel: row.assignee?.name ?? "Unassigned",
    strategy: row.strategy,
  };
}

export function mapOperationalModelToQueueRow(
  model: FulfillmentOperationalModel,
  now = Date.now(),
): FulfillmentQueueRow {
  const product = model.order?.product;
  const source = model.order?.source ?? model.order?.journey;
  const journeyKey = resolveFulfillmentJourneyKey(model.fulfillment.strategy, source);
  const { label, category } = resolveRequiredAction({
    status: model.fulfillment.status,
    strategy: model.fulfillment.strategy,
    source,
    delivery_type: model.order?.delivery_type,
    last_mile_receiving_method: model.order?.last_mile_receiving_method,
    china: model.china,
    warehouse: model.warehouse,
    shipment: model.shipment,
  });

  return {
    id: model.fulfillment.id,
    orderId: model.order?.id ?? "",
    orderNumber: model.order?.order_number ?? "—",
    customerName: model.order?.customer?.name ?? "—",
    productName: product?.name ?? "—",
    productVariant: product?.variant_label ?? undefined,
    productQuantity: product?.quantity ?? 0,
    productImageUrl: product?.image_url,
    additionalItemCount: product?.additional_item_count ?? 0,
    journeyLabel: resolveFulfillmentJourneyLabel(model.fulfillment.strategy, source),
    journeyKey,
    currentStage: resolveFulfillmentStatusLabel(
      model.fulfillment.status,
      model.fulfillment.status_label,
    ),
    status: model.fulfillment.status,
    requiredAction: label,
    actionCategory: category,
    ageLabel: formatFulfillmentAge(model.fulfillment.created_at, now),
    ageMs: fulfillmentAgeMs(model.fulfillment.created_at, now),
    assignedLabel: model.fulfillment.assignee?.name ?? "Unassigned",
    strategy: model.fulfillment.strategy,
  };
}

export function filterQueueRows(
  rows: FulfillmentQueueRow[],
  filters: {
    journey: FulfillmentJourneyFilter;
    status: string;
    actionRequired: FulfillmentActionRequiredFilter;
    search?: string;
  },
): FulfillmentQueueRow[] {
  return rows.filter((row) => {
    if (filters.journey !== "all" && row.journeyKey !== filters.journey) {
      return false;
    }
    if (filters.status !== "all" && row.status !== filters.status) {
      return false;
    }
    if (filters.actionRequired !== "all" && row.actionCategory !== filters.actionRequired) {
      return false;
    }
    if (filters.search && !matchesQueueSearch(row, filters.search)) {
      return false;
    }
    return true;
  });
}

export type FulfillmentTimelineStep = {
  status: string;
  label: string;
  timestamp: string | null;
  sourceLabel: string;
  actorLabel: string;
  notes?: string | null;
};

function resolveHistoryActorLabel(entry: FulfillmentStatusHistoryEntry): string {
  if (entry.changed_by_admin?.name) {
    return entry.changed_by_admin.name;
  }
  if (entry.changed_by?.trim()) {
    return entry.changed_by.trim();
  }
  return resolveHistorySourceLabel(entry.source);
}

export function buildFulfillmentTimelineSteps(
  model: FulfillmentOperationalModel,
): FulfillmentTimelineStep[] {
  const history = model.status_history;
  if (history.length > 0) {
    return history.map((entry) => ({
      status: entry.to_status,
      label: resolveFulfillmentStatusLabel(entry.to_status),
      timestamp: entry.created_at ?? null,
      sourceLabel: resolveHistorySourceLabel(entry.source),
      actorLabel: resolveHistoryActorLabel(entry),
      notes: entry.notes ?? null,
    }));
  }

  return [
    {
      status: model.fulfillment.status,
      label: resolveFulfillmentStatusLabel(
        model.fulfillment.status,
        model.fulfillment.status_label,
      ),
      timestamp: model.fulfillment.updated_at ?? model.fulfillment.created_at ?? null,
      sourceLabel: "Current state",
      actorLabel: model.fulfillment.assignee?.name ?? "System",
    },
  ];
}

export function isChinaImportStrategy(strategy: string): boolean {
  return resolveFulfillmentJourneyKey(strategy) === "china";
}

export const FULFILLMENT_STATUS_STYLES: Record<string, string> = {
  pending: "bg-amber-50 text-amber-800 ring-amber-600/20",
  processing: "bg-blue-50 text-blue-800 ring-blue-600/20",
  ready_for_shipping: "bg-indigo-50 text-indigo-800 ring-indigo-600/20",
  shipped: "bg-violet-50 text-violet-800 ring-violet-600/20",
  delivered: "bg-green-50 text-green-800 ring-green-600/20",
  cancelled: "bg-zinc-100 text-zinc-600 ring-zinc-300/40",
};
