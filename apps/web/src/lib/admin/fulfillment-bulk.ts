import { hasFulfillmentPermission, resolveChinaPackingAdvanceStatuses } from "@/lib/admin/fulfillment-available-actions";
import { isEligibleForMarkExportReady } from "@/lib/admin/fulfillment-export-eligibility";
import type { AdminFulfillment, AdminFulfillmentChinaSummary } from "@/lib/api/admin-fulfillments";

/** Bulk action keys for TZ_LOCAL and China Import bulk fulfilment operations. */
export type FulfillmentBulkActionKey =
  | "MARK_LOCAL_ORDER_READY"
  | "MARK_LOCAL_ORDER_COMPLETED"
  | "CREATE_SUPPLIER_PURCHASE"
  | "CONFIRM_SUPPLIER_PURCHASE"
  | "RECEIVE_GOODS"
  | "MARK_QC_PASSED"
  | "MARK_CHINA_PACKING_COMPLETE"
  | "MARK_EXPORT_READY"
  | "MARK_AGENT_DELIVERED"
  | "MARK_CUSTOMER_COLLECTED"
  | "MARK_CUSTOMER_DELIVERED"
  | "CREATE_SHIPMENT";

export type FulfillmentBulkActionDefinition = {
  key: FulfillmentBulkActionKey;
  label: string;
  description: string;
  requires_confirmation: boolean;
  confirmation_title: string;
  confirmation_message: string;
  /** All listed permissions are required before the action is shown. */
  permissions: string[];
  execution_enabled: boolean;
};

export type ChinaBulkSummary = {
  stage?: string | null;
  qc_status?: string | null;
  export_ready?: boolean;
  has_supplier_purchase?: boolean;
  purchase_receivable?: boolean;
  supplier_purchase_state?: string | null;
};

export type BulkSelectionContext = {
  id: string;
  strategy: string;
  status: string;
  delivery_type?: string | null;
  warehouse_status?: string | null;
  shipment_status?: string | null;
  shipment_arrived_at?: string | null;
  last_mile_receiving_method?: string | null;
  china?: ChinaBulkSummary | null;
};

export type BulkOperationStatus = "pending" | "processing" | "completed" | "failed";

export type BulkOperationRecord = {
  batch_id: string;
  action_key: FulfillmentBulkActionKey;
  admin_id: string;
  requested_count: number;
  started_at: string;
  status: BulkOperationStatus;
};

export type BulkOperationResultSummary = {
  batch_id: string;
  action_key: FulfillmentBulkActionKey;
  admin_id: string;
  requested_count: number;
  succeeded_count: number;
  failed_count: number;
  skipped_count: number;
  started_at: string;
  completed_at?: string;
  status: BulkOperationStatus;
};

export type BulkFulfillmentItemStatus = "succeeded" | "failed" | "skipped";

export type BulkFulfillmentActionItemResult = {
  fulfillment_id: string;
  status: BulkFulfillmentItemStatus;
  success: boolean;
  reason_code?: string;
  reason?: string;
  /** @deprecated Use `reason` */
  error?: string;
};

export const BULK_LARGE_SELECTION_WARNING_THRESHOLD = 50;

export type BulkFulfillmentActionResponse = {
  batch_id: string;
  action_key: FulfillmentBulkActionKey;
  total: number;
  succeeded: number;
  failed: number;
  skipped: number;
  results: BulkFulfillmentActionItemResult[];
};

const LOCAL_READY_DELIVERY_TYPES = new Set(["self_pickup", "negotiated_delivery"]);
const TERMINAL_FULFILLMENT_STATUSES = new Set(["delivered", "cancelled"]);

const LOCAL_FULFILLMENT_BULK_ACTIONS: FulfillmentBulkActionDefinition[] = [
  {
    key: "MARK_LOCAL_ORDER_READY",
    label: "Mark order ready",
    description:
      "Complete order preparation and notify customers for selected Buy From TZ orders.",
    requires_confirmation: true,
    confirmation_title: "Prepare selected orders?",
    confirmation_message:
      "You are about to mark local orders as ready. This will complete warehouse preparation and notify customers.",
    permissions: ["warehouse.jobs.update", "warehouse.jobs.complete"],
    execution_enabled: true,
  },
  {
    key: "MARK_LOCAL_ORDER_COMPLETED",
    label: "Mark order completed",
    description:
      "Mark selected Buy From TZ orders as completed after they are order-ready.",
    requires_confirmation: true,
    confirmation_title: "Complete selected orders?",
    confirmation_message:
      "You are about to mark local orders as completed. Customers will receive completion notifications.",
    permissions: ["orders.fulfill"],
    execution_enabled: true,
  },
];

const CHINA_FULFILLMENT_BULK_ACTIONS: FulfillmentBulkActionDefinition[] = [
  {
    key: "CREATE_SUPPLIER_PURCHASE",
    label: "Create supplier purchase",
    description: "Generate supplier purchase orders for selected China Import orders.",
    requires_confirmation: true,
    confirmation_title: "Create supplier purchases?",
    confirmation_message:
      "You are about to create supplier purchases for selected China Import orders.",
    permissions: ["procurement.create"],
    execution_enabled: true,
  },
  {
    key: "CONFIRM_SUPPLIER_PURCHASE",
    label: "Confirm supplier purchase",
    description: "Record supplier acceptance for pending China purchase orders.",
    requires_confirmation: true,
    confirmation_title: "Confirm supplier purchases?",
    confirmation_message:
      "You are about to confirm supplier purchases for selected China Import orders.",
    permissions: ["procurement.update"],
    execution_enabled: false,
  },
  {
    key: "RECEIVE_GOODS",
    label: "Receive goods",
    description: "Receive goods against eligible China purchase orders.",
    requires_confirmation: true,
    confirmation_title: "Receive goods for selected orders?",
    confirmation_message:
      "You are about to mark goods as received for selected China Import orders.",
    permissions: ["purchase_orders.receive"],
    execution_enabled: true,
  },
  {
    key: "MARK_QC_PASSED",
    label: "Mark QC passed",
    description:
      "Record that physical quality checks are complete for selected China Import orders.",
    requires_confirmation: true,
    confirmation_title: "Mark selected orders as QC passed?",
    confirmation_message:
      "You are confirming that physical quality checks have been completed for selected China Import orders.",
    permissions: ["procurement.update"],
    execution_enabled: true,
  },
  {
    key: "MARK_CHINA_PACKING_COMPLETE",
    label: "Mark packing complete",
    description: "Advance warehouse preparation for selected China Import orders through to packed.",
    requires_confirmation: true,
    confirmation_title: "Complete packing for selected China orders?",
    confirmation_message:
      "You are about to complete warehouse packing for selected China Import orders.",
    permissions: ["warehouse.jobs.update", "warehouse.jobs.complete"],
    execution_enabled: true,
  },
  {
    key: "MARK_EXPORT_READY",
    label: "Mark export ready",
    description: "Mark selected China company shipping orders export-ready after checklist approval.",
    requires_confirmation: true,
    confirmation_title: "Mark selected China shipments as export ready?",
    confirmation_message:
      "You are about to mark selected China company shipping orders export-ready.",
    permissions: ["warehouse.jobs.complete"],
    execution_enabled: true,
  },
  {
    key: "MARK_AGENT_DELIVERED",
    label: "Deliver to customer agent",
    description:
      "Confirm delivery to the customer's nominated agent for selected China Import orders.",
    requires_confirmation: true,
    confirmation_title: "Deliver selected orders to customer agents?",
    confirmation_message: "Deliver selected orders to customer agents?",
    permissions: ["orders.ship"],
    execution_enabled: true,
  },
  {
    key: "CREATE_SHIPMENT",
    label: "Create shipments",
    description: "Create shipments for export-ready China company shipping orders.",
    requires_confirmation: true,
    confirmation_title: "Create shipments for selected China orders?",
    confirmation_message: "Create shipments for selected China orders?",
    permissions: ["orders.ship"],
    execution_enabled: true,
  },
  {
    key: "MARK_CUSTOMER_COLLECTED",
    label: "Mark customers collected",
    description:
      "Confirm customer collection and complete selected China company shipping orders.",
    requires_confirmation: true,
    confirmation_title: "Confirm selected customers collected?",
    confirmation_message:
      "You are about to confirm customer collection for selected China company shipping orders.",
    permissions: ["orders.fulfill"],
    execution_enabled: true,
  },
  {
    key: "MARK_CUSTOMER_DELIVERED",
    label: "Mark customers delivered",
    description:
      "Confirm customer delivery and complete selected China company shipping orders.",
    requires_confirmation: true,
    confirmation_title: "Confirm selected customers delivered?",
    confirmation_message:
      "You are about to confirm customer delivery for selected China company shipping orders.",
    permissions: ["orders.fulfill"],
    execution_enabled: true,
  },
];

export const FULFILLMENT_BULK_ACTIONS: FulfillmentBulkActionDefinition[] = [
  ...LOCAL_FULFILLMENT_BULK_ACTIONS,
  ...CHINA_FULFILLMENT_BULK_ACTIONS,
];

export function parseChinaBulkSummary(
  china?: AdminFulfillmentChinaSummary | null,
): ChinaBulkSummary | null {
  if (!china) {
    return null;
  }

  return {
    stage: china.stage ?? null,
    qc_status: china.qc_status ?? null,
    export_ready: china.export_ready === true,
    has_supplier_purchase: china.has_supplier_purchase === true,
    purchase_receivable: china.purchase_receivable === true,
    supplier_purchase_state: china.supplier_purchase_state ?? null,
  };
}

export function mapAdminFulfillmentToBulkSelectionContext(
  row: Pick<
    AdminFulfillment,
    "id" | "strategy" | "status" | "warehouse_status" | "shipment_status" | "shipment_arrived_at" | "china" | "order"
  >,
): BulkSelectionContext {
  return {
    id: row.id,
    strategy: row.strategy,
    status: row.status,
    delivery_type: row.order?.delivery_type ?? null,
    warehouse_status: row.warehouse_status ?? null,
    shipment_status: row.shipment_status ?? null,
    shipment_arrived_at: row.shipment_arrived_at ?? null,
    last_mile_receiving_method: row.order?.last_mile_receiving_method ?? null,
    china: parseChinaBulkSummary(row.china),
  };
}

export function isChinaImportFulfillment(selection: BulkSelectionContext): boolean {
  return selection.strategy === "china";
}

export function isChinaQcPassed(selection: BulkSelectionContext): boolean {
  return (selection.china?.qc_status ?? "").toLowerCase() === "passed";
}

export function isChinaExportReady(selection: BulkSelectionContext): boolean {
  return selection.china?.export_ready === true;
}

export function hasSupplierPurchase(selection: BulkSelectionContext): boolean {
  return selection.china?.has_supplier_purchase === true;
}

export function isPurchaseReceivable(selection: BulkSelectionContext): boolean {
  return selection.china?.purchase_receivable === true;
}

export function isEligibleForBulkReceiveGoods(selection: BulkSelectionContext): boolean {
  if (!isChinaImportFulfillment(selection)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  const deliveryType = (selection.delivery_type ?? "").toLowerCase();
  if (deliveryType === "customer_agent") {
    return false;
  }

  if (!hasSupplierPurchase(selection)) {
    return false;
  }

  if (!isPurchaseReceivable(selection)) {
    return false;
  }

  const stage = (selection.china?.stage ?? "").toLowerCase();
  if (!["procurement_in_progress", "partially_received"].includes(stage)) {
    return false;
  }

  return true;
}

export function countEligibleForBulkReceiveGoods(selected: BulkSelectionContext[]): number {
  return selected.filter(isEligibleForBulkReceiveGoods).length;
}

const QC_READY_STAGES = new Set(["received", "partially_received", "qc_pending"]);

export function isEligibleForBulkMarkQcPassed(selection: BulkSelectionContext): boolean {
  if (!isChinaImportFulfillment(selection)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  if (isChinaQcPassed(selection)) {
    return false;
  }

  const qcStatus = (selection.china?.qc_status ?? "pending").toLowerCase();
  if (qcStatus !== "pending") {
    return false;
  }

  const stage = (selection.china?.stage ?? "").toLowerCase();
  if (!QC_READY_STAGES.has(stage)) {
    return false;
  }

  return true;
}

export function countEligibleForBulkMarkQcPassed(selected: BulkSelectionContext[]): number {
  return selected.filter(isEligibleForBulkMarkQcPassed).length;
}

const CHINA_WAREHOUSE_PREP_STAGES = new Set([
  "qc_passed",
  "consolidated",
  "consolidating",
  "export_ready",
  "company_shipping_ready",
]);

export function isEligibleForBulkMarkChinaPackingComplete(
  selection: BulkSelectionContext,
): boolean {
  if (!isChinaImportFulfillment(selection)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  if (!isChinaQcPassed(selection)) {
    return false;
  }

  const stage = (selection.china?.stage ?? "").toLowerCase();
  if (!CHINA_WAREHOUSE_PREP_STAGES.has(stage)) {
    return false;
  }

  const warehouseStatus = (selection.warehouse_status ?? "").toLowerCase();
  if (["packed", "ready_to_ship", "cancelled"].includes(warehouseStatus)) {
    return false;
  }

  return resolveChinaPackingAdvanceStatuses(warehouseStatus).length > 0;
}

export function countEligibleForBulkMarkChinaPackingComplete(
  selected: BulkSelectionContext[],
): number {
  return selected.filter(isEligibleForBulkMarkChinaPackingComplete).length;
}

export function isEligibleForBulkMarkExportReady(selection: BulkSelectionContext): boolean {
  return isEligibleForMarkExportReady({
    strategy: selection.strategy,
    status: selection.status,
    delivery_type: selection.delivery_type,
    warehouse_status: selection.warehouse_status,
    china: selection.china,
  });
}

export function countEligibleForBulkMarkExportReady(selected: BulkSelectionContext[]): number {
  return selected.filter(isEligibleForBulkMarkExportReady).length;
}

const AGENT_HANDOVER_WAREHOUSE_STATUSES = new Set(["packed", "ready_to_ship"]);

const AGENT_HANDOVER_WORKFLOW_STAGES = new Set([
  "qc_passed",
  "consolidated",
  "consolidating",
  "export_ready",
  "company_shipping_ready",
]);

export function isEligibleForBulkMarkAgentDelivered(selection: BulkSelectionContext): boolean {
  if (!isChinaImportFulfillment(selection)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  const deliveryType = (selection.delivery_type ?? "").toLowerCase();
  if (deliveryType !== "customer_agent") {
    return false;
  }

  if (!isChinaQcPassed(selection)) {
    return false;
  }

  const stage = (selection.china?.stage ?? "").toLowerCase();
  if (!AGENT_HANDOVER_WORKFLOW_STAGES.has(stage)) {
    return false;
  }

  const warehouseStatus = (selection.warehouse_status ?? "").toLowerCase();
  if (!AGENT_HANDOVER_WAREHOUSE_STATUSES.has(warehouseStatus)) {
    return false;
  }

  return true;
}

export function countEligibleForBulkMarkAgentDelivered(selected: BulkSelectionContext[]): number {
  return selected.filter(isEligibleForBulkMarkAgentDelivered).length;
}

export function hasExistingShipment(selection: BulkSelectionContext): boolean {
  return (selection.shipment_status ?? "").trim() !== "";
}

export function isEligibleForBulkCreateShipment(selection: BulkSelectionContext): boolean {
  if (!isChinaImportFulfillment(selection)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  if (selection.status === "shipped") {
    return false;
  }

  const deliveryType = (selection.delivery_type ?? "").toLowerCase();
  if (deliveryType !== "company_shipping") {
    return false;
  }

  if (!isChinaExportReady(selection)) {
    return false;
  }

  if (hasExistingShipment(selection)) {
    return false;
  }

  if (selection.status !== "ready_for_shipping") {
    return false;
  }

  const warehouseStatus = (selection.warehouse_status ?? "").toLowerCase();
  if (warehouseStatus !== "ready_to_ship") {
    return false;
  }

  return true;
}

export function countEligibleForBulkCreateShipment(selected: BulkSelectionContext[]): number {
  return selected.filter(isEligibleForBulkCreateShipment).length;
}

export function isEligibleForBulkCreateSupplierPurchase(
  selection: BulkSelectionContext,
): boolean {
  if (!isChinaImportFulfillment(selection)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  const deliveryType = (selection.delivery_type ?? "").toLowerCase();
  if (deliveryType === "customer_agent") {
    return false;
  }

  if (hasSupplierPurchase(selection)) {
    return false;
  }

  const stage = (selection.china?.stage ?? "").toLowerCase();
  if (stage !== "" && stage !== "awaiting_procurement") {
    return false;
  }

  return true;
}

export function countEligibleForBulkCreateSupplierPurchase(
  selected: BulkSelectionContext[],
): number {
  return selected.filter(isEligibleForBulkCreateSupplierPurchase).length;
}

function isEligibleForBulkCompanyHandover(
  selection: BulkSelectionContext,
  expectedMethod: "self_pickup" | "negotiated_delivery",
): boolean {
  if (!isChinaImportFulfillment(selection)) {
    return false;
  }

  if ((selection.delivery_type ?? "").toLowerCase() !== "company_shipping") {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  if (selection.status !== "shipped") {
    return false;
  }

  if (!selection.shipment_arrived_at) {
    return false;
  }

  if ((selection.last_mile_receiving_method ?? "").toLowerCase() !== expectedMethod) {
    return false;
  }

  return true;
}

export function isEligibleForBulkMarkCustomerCollected(
  selection: BulkSelectionContext,
): boolean {
  return isEligibleForBulkCompanyHandover(selection, "self_pickup");
}

export function isEligibleForBulkMarkCustomerDelivered(
  selection: BulkSelectionContext,
): boolean {
  return isEligibleForBulkCompanyHandover(selection, "negotiated_delivery");
}

export function countEligibleForBulkMarkCustomerCollected(
  selected: BulkSelectionContext[],
): number {
  return selected.filter(isEligibleForBulkMarkCustomerCollected).length;
}

export function countEligibleForBulkMarkCustomerDelivered(
  selected: BulkSelectionContext[],
): number {
  return selected.filter(isEligibleForBulkMarkCustomerDelivered).length;
}

export function canBulkChinaAction(
  actionKey: FulfillmentBulkActionKey,
  selection: BulkSelectionContext,
  permissions?: string[],
): boolean {
  const action = FULFILLMENT_BULK_ACTIONS.find((candidate) => candidate.key === actionKey);
  if (!action || !action.execution_enabled) {
    return false;
  }

  if (!isChinaImportFulfillment(selection)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  if (!hasBulkActionPermissions(action, permissions)) {
    return false;
  }

  if (actionKey === "CREATE_SUPPLIER_PURCHASE") {
    return isEligibleForBulkCreateSupplierPurchase(selection);
  }

  if (actionKey === "RECEIVE_GOODS") {
    return isEligibleForBulkReceiveGoods(selection);
  }

  if (actionKey === "MARK_QC_PASSED") {
    return isEligibleForBulkMarkQcPassed(selection);
  }

  if (actionKey === "MARK_CHINA_PACKING_COMPLETE") {
    return isEligibleForBulkMarkChinaPackingComplete(selection);
  }

  if (actionKey === "MARK_EXPORT_READY") {
    return isEligibleForBulkMarkExportReady(selection);
  }

  if (actionKey === "MARK_AGENT_DELIVERED") {
    return isEligibleForBulkMarkAgentDelivered(selection);
  }

  if (actionKey === "CREATE_SHIPMENT") {
    return isEligibleForBulkCreateShipment(selection);
  }

  if (actionKey === "MARK_CUSTOMER_COLLECTED") {
    return isEligibleForBulkMarkCustomerCollected(selection);
  }

  if (actionKey === "MARK_CUSTOMER_DELIVERED") {
    return isEligibleForBulkMarkCustomerDelivered(selection);
  }

  return true;
}

export function isEligibleForBulkLocalReady(selection: BulkSelectionContext): boolean {
  if (selection.strategy !== "local") {
    return false;
  }

  const deliveryType = (selection.delivery_type ?? "").toLowerCase();
  if (!LOCAL_READY_DELIVERY_TYPES.has(deliveryType)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  if (selection.status === "ready_for_shipping") {
    return false;
  }

  return true;
}

export function countEligibleForBulkLocalReady(selected: BulkSelectionContext[]): number {
  return selected.filter(isEligibleForBulkLocalReady).length;
}

export function isEligibleForBulkLocalCompleted(selection: BulkSelectionContext): boolean {
  if (selection.strategy !== "local") {
    return false;
  }

  const deliveryType = (selection.delivery_type ?? "").toLowerCase();
  if (!LOCAL_READY_DELIVERY_TYPES.has(deliveryType)) {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(selection.status)) {
    return false;
  }

  if (selection.status !== "ready_for_shipping") {
    return false;
  }

  const warehouseStatus = (selection.warehouse_status ?? "").toLowerCase();
  if (warehouseStatus !== "" && warehouseStatus !== "ready_to_ship") {
    return false;
  }

  return true;
}

export function countEligibleForBulkLocalCompleted(selected: BulkSelectionContext[]): number {
  return selected.filter(isEligibleForBulkLocalCompleted).length;
}

export function hasBulkActionPermissions(
  action: FulfillmentBulkActionDefinition,
  permissions?: string[],
): boolean {
  return action.permissions.every((permission) =>
    hasFulfillmentPermission(permissions, permission),
  );
}

export function resolveVisibleBulkActions(
  permissions?: string[],
): FulfillmentBulkActionDefinition[] {
  return FULFILLMENT_BULK_ACTIONS.filter(
    (action) => action.execution_enabled && hasBulkActionPermissions(action, permissions),
  );
}

export function resolveVisibleBulkActionsForSelection(
  permissions: string[] | undefined,
  selected: BulkSelectionContext[],
): FulfillmentBulkActionDefinition[] {
  return resolveVisibleBulkActions(permissions).filter((action) => {
    if (action.key === "MARK_LOCAL_ORDER_READY") {
      return countEligibleForBulkLocalReady(selected) > 0;
    }
    if (action.key === "MARK_LOCAL_ORDER_COMPLETED") {
      return countEligibleForBulkLocalCompleted(selected) > 0;
    }
    if (action.key === "CREATE_SUPPLIER_PURCHASE") {
      return countEligibleForBulkCreateSupplierPurchase(selected) > 0;
    }
    if (action.key === "RECEIVE_GOODS") {
      return countEligibleForBulkReceiveGoods(selected) > 0;
    }
    if (action.key === "MARK_QC_PASSED") {
      return countEligibleForBulkMarkQcPassed(selected) > 0;
    }
    if (action.key === "MARK_CHINA_PACKING_COMPLETE") {
      return countEligibleForBulkMarkChinaPackingComplete(selected) > 0;
    }
    if (action.key === "MARK_EXPORT_READY") {
      return countEligibleForBulkMarkExportReady(selected) > 0;
    }
    if (action.key === "MARK_AGENT_DELIVERED") {
      return countEligibleForBulkMarkAgentDelivered(selected) > 0;
    }
    if (action.key === "CREATE_SHIPMENT") {
      return countEligibleForBulkCreateShipment(selected) > 0;
    }
    if (action.key === "MARK_CUSTOMER_COLLECTED") {
      return countEligibleForBulkMarkCustomerCollected(selected) > 0;
    }
    if (action.key === "MARK_CUSTOMER_DELIVERED") {
      return countEligibleForBulkMarkCustomerDelivered(selected) > 0;
    }
    return true;
  });
}

export function shouldShowBulkActionBar(
  selectedCount: number,
  permissions?: string[],
  selected: BulkSelectionContext[] = [],
): boolean {
  return (
    selectedCount > 0 && resolveVisibleBulkActionsForSelection(permissions, selected).length > 0
  );
}

export function buildBulkLocalReadyConfirmationMessage(eligibleCount: number): string {
  return `You are about to mark ${eligibleCount} local order${
    eligibleCount === 1 ? "" : "s"
  } as ready. This will complete warehouse preparation and notify customers.`;
}

export function buildBulkLocalCompletedConfirmationMessage(eligibleCount: number): string {
  return `You are about to mark ${eligibleCount} local order${
    eligibleCount === 1 ? "" : "s"
  } as completed. Customers will receive completion notifications.`;
}

export function buildBulkCreateSupplierPurchaseConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are about to create supplier purchases for ${eligibleCount} China ${orderLabel}.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function buildBulkReceiveGoodsConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are about to mark goods as received for ${eligibleCount} China ${orderLabel}.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function buildBulkMarkQcPassedConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are confirming that physical quality checks have been completed for ${eligibleCount} China ${orderLabel}.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function buildBulkMarkChinaPackingCompleteConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are about to complete warehouse packing for ${eligibleCount} China ${orderLabel}.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function buildBulkMarkExportReadyConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are about to mark ${eligibleCount} China ${orderLabel} as export ready.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function buildBulkMarkAgentDeliveredConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are about to deliver ${eligibleCount} China ${orderLabel} to customer agents.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function buildBulkCreateShipmentConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are about to create shipments for ${eligibleCount} China ${orderLabel}.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function buildBulkMarkCustomerCollectedConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are about to confirm customer collection for ${eligibleCount} China ${orderLabel}.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function buildBulkMarkCustomerDeliveredConfirmationMessage(
  eligibleCount: number,
  skippedCount: number,
): string {
  const orderLabel = eligibleCount === 1 ? "order" : "orders";
  let message = `You are about to confirm customer delivery for ${eligibleCount} China ${orderLabel}.`;
  if (skippedCount > 0) {
    message += ` ${skippedCount} selected ${skippedCount === 1 ? "order" : "orders"} will be skipped.`;
  }
  return message;
}

export function resolveBulkSuccessLabel(actionKey: FulfillmentBulkActionKey): string {
  if (actionKey === "CREATE_SUPPLIER_PURCHASE") {
    return "Created";
  }
  if (actionKey === "CREATE_SHIPMENT") {
    return "Created";
  }
  if (actionKey === "RECEIVE_GOODS") {
    return "Received";
  }
  if (actionKey === "MARK_QC_PASSED") {
    return "QC Passed";
  }
  if (actionKey === "MARK_CHINA_PACKING_COMPLETE") {
    return "Packed";
  }
  if (actionKey === "MARK_EXPORT_READY") {
    return "Export Ready";
  }
  if (actionKey === "MARK_AGENT_DELIVERED") {
    return "Delivered";
  }
  if (actionKey === "MARK_CUSTOMER_COLLECTED" || actionKey === "MARK_CUSTOMER_DELIVERED") {
    return "Completed";
  }
  return "Completed";
}

export function shouldClearBulkSelectionAfterSuccess(
  response: BulkFulfillmentActionResponse,
): boolean {
  return response.succeeded > 0;
}

const BULK_RESULT_REASON_LABELS: Record<string, string> = {
  ALREADY_COMPLETED: "Already completed",
  ALREADY_EXPORT_READY: "Already export ready",
  ALREADY_SHIPPED: "Already shipped",
  CHECKLIST_INCOMPLETE: "Export checklist incomplete",
  FULFILLMENT_NOT_FOUND: "Fulfilment not found",
  INVALID_METHOD: "Wrong receiving method",
  MISSING_SUPPLIER: "Missing supplier mapping",
  NO_RECEIVING_METHOD: "No receiving method selected",
  NOT_ARRIVED: "Shipment not arrived",
  NOT_COMPANY_SHIPPING: "Not company shipping",
  NOT_ELIGIBLE: "Not eligible",
  NOT_ELIGIBLE_QC: "QC not passed",
  NOT_ELIGIBLE_STAGE: "Not eligible stage",
  ORDER_NOT_FOUND: "Order not found",
  SHIPMENT_EXISTS: "Shipment already exists",
  VALIDATION_FAILED: "Validation failed",
  WAREHOUSE_NOT_READY: "Warehouse not ready",
  WRONG_DELIVERY_TYPE: "Wrong delivery type",
  WRONG_STRATEGY: "Wrong fulfilment strategy",
};

export function resolveBulkResultReasonLabel(reasonCode: string): string {
  return BULK_RESULT_REASON_LABELS[reasonCode] ?? reasonCode.replaceAll("_", " ").toLowerCase();
}

export function groupBulkResultReasons(
  results: BulkFulfillmentActionItemResult[],
  status: BulkFulfillmentItemStatus,
): Array<{ reason_code: string; label: string; count: number }> {
  const counts = new Map<string, number>();

  for (const row of results) {
    if (row.status !== status) {
      continue;
    }
    const code = row.reason_code ?? "UNKNOWN";
    counts.set(code, (counts.get(code) ?? 0) + 1);
  }

  return Array.from(counts.entries())
    .map(([reason_code, count]) => ({
      reason_code,
      label: resolveBulkResultReasonLabel(reason_code),
      count,
    }))
    .sort((left, right) => right.count - left.count || left.label.localeCompare(right.label));
}

export function shouldWarnLargeBulkSelection(selectedCount: number): boolean {
  return selectedCount > BULK_LARGE_SELECTION_WARNING_THRESHOLD;
}

export function buildLargeBulkSelectionWarning(selectedCount: number): string {
  return `You selected more than ${BULK_LARGE_SELECTION_WARNING_THRESHOLD} orders. This operation may take longer.`;
}

export function createBulkOperationDraft(input: {
  actionKey: FulfillmentBulkActionKey;
  adminId: string;
  fulfillmentIds: string[];
  batchId?: string;
}): BulkOperationRecord {
  return {
    batch_id: input.batchId ?? crypto.randomUUID(),
    action_key: input.actionKey,
    admin_id: input.adminId,
    requested_count: input.fulfillmentIds.length,
    started_at: new Date().toISOString(),
    status: "pending",
  };
}

export function summarizeBulkOperation(
  record: BulkOperationRecord,
  results: {
    succeeded: number;
    failed: number;
    skipped: number;
  },
): BulkOperationResultSummary {
  const requested = record.requested_count;
  const processed = results.succeeded + results.failed + results.skipped;
  const status: BulkOperationStatus =
    processed >= requested ? "completed" : results.failed > 0 ? "failed" : "processing";

  return {
    batch_id: record.batch_id,
    action_key: record.action_key,
    admin_id: record.admin_id,
    requested_count: requested,
    succeeded_count: results.succeeded,
    failed_count: results.failed,
    skipped_count: results.skipped,
    started_at: record.started_at,
    completed_at: status === "completed" ? new Date().toISOString() : undefined,
    status,
  };
}
