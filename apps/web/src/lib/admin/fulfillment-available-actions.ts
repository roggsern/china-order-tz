import {
  isChinaImportStrategy,
  type FulfillmentOperationalModel,
} from "@/lib/admin/fulfillment-operational";
import { isEligibleForMarkExportReady } from "@/lib/admin/fulfillment-export-eligibility";

export type FulfillmentActionKey =
  | "CREATE_PURCHASE"
  | "CONFIRM_PURCHASE"
  | "RECEIVE_GOODS"
  | "START_QC"
  | "COMPLETE_PACKING"
  | "MARK_LOCAL_ORDER_READY"
  | "MARK_EXPORT_READY"
  | "CREATE_SHIPMENT"
  | "DISPATCH_SHIPMENT"
  | "CONFIRM_ARRIVED_TANZANIA"
  | "MARK_READY"
  | "ASSIGN_DELIVERY"
  | "COMPLETE_DELIVERY"
  | "COMPLETE_LOCAL_ORDER"
  | "MARK_CUSTOMER_COLLECTED"
  | "MARK_CUSTOMER_DELIVERED"
  | "AGENT_BOOTSTRAP"
  | "MARK_AGENT_DELIVERED"
  /** @internal Legacy backend orchestration keys — not shown in admin UI. */
  | "AGENT_AUTHORIZE"
  | "AGENT_SCHEDULE"
  | "AGENT_RELEASE"
  | "AGENT_HANDOVER";

export type FulfillmentAvailableAction = {
  key: FulfillmentActionKey;
  label: string;
  description: string;
  requires_confirmation: boolean;
  confirmation_title?: string;
  confirmation_message?: string;
  available: boolean;
  unavailable_reason?: string;
  permission?: string;
  meta?: Record<string, unknown>;
};

export type CustomerAgentOperationalState = {
  id?: string;
  authorization_status?: string | null;
  release_status?: string | null;
  pickup_status?: string | null;
  handover_completed_at?: string | null;
} | null;

export type FulfillmentActionPurchaseOrder = {
  id: string;
  status?: string | null;
  supplier_response?: string | null;
  purchase_number?: string | null;
};

export type FulfillmentAvailableActionsInput = {
  model: FulfillmentOperationalModel;
  permissions?: string[];
  customerAgent?: CustomerAgentOperationalState;
  purchaseOrders?: FulfillmentActionPurchaseOrder[];
};

const TERMINAL_FULFILLMENT_STATUSES = new Set(["delivered", "cancelled"]);

const COMPANY_SHIPPING_DELIVERY_TYPES = new Set([
  "company_shipping",
  "negotiated_delivery",
]);

const WAREHOUSE_FORWARD: Record<string, string | null> = {
  pending: "picking",
  picking: "picked",
  picked: "packing",
  packing: "packed",
  packed: "ready_to_ship",
  ready_to_ship: null,
  cancelled: null,
};

const CHINA_WAREHOUSE_STAGES = new Set([
  "qc_passed",
  "consolidated",
  "consolidating",
  "export_ready",
  "company_shipping_ready",
]);

const CHINA_PRE_PACKED_WAREHOUSE_STATUSES = new Set([
  "pending",
  "picking",
  "picked",
  "packing",
]);

const LOCAL_PRE_READY_WAREHOUSE_STATUSES = new Set([
  "pending",
  "picking",
  "picked",
  "packing",
  "packed",
]);

const AGENT_ACTION_PRIORITY: FulfillmentActionKey[] = ["AGENT_BOOTSTRAP", "MARK_AGENT_DELIVERED"];

const AGENT_HANDOVER_WAREHOUSE_STATUSES = new Set(["packed", "ready_to_ship"]);

const MARK_AGENT_DELIVERED_IMPACT =
  "Updates the customer agent delivery progress.";

function isWarehouseReadyForAgentHandover(model: FulfillmentOperationalModel): boolean {
  const warehouseStatus = (model.warehouse?.status ?? "").toLowerCase();
  if (!warehouseStatus) {
    return false;
  }

  return AGENT_HANDOVER_WAREHOUSE_STATUSES.has(warehouseStatus);
}

function isChinaPreparationCompleteForAgentHandover(model: FulfillmentOperationalModel): boolean {
  const qcPassed = (model.china?.qc_status ?? "").toLowerCase() === "passed";
  if (!qcPassed) {
    return false;
  }

  const stage = (model.china?.stage ?? "").toLowerCase();
  return stage === "qc_passed" || CHINA_WAREHOUSE_STAGES.has(stage);
}

/** Seller can confirm handover to the customer's nominated agent (not company shipping). */
export function isSellerReadyToConfirmAgentDelivery(
  model: FulfillmentOperationalModel,
): boolean {
  const { fulfillment } = model;

  if (isTerminalFulfillment(fulfillment.status)) {
    return false;
  }

  if (isChinaImportStrategy(fulfillment.strategy)) {
    if (!isChinaPreparationCompleteForAgentHandover(model)) {
      return false;
    }
  }

  return isWarehouseReadyForAgentHandover(model);
}

function resolveAgentHandoverUnavailableReason(model: FulfillmentOperationalModel): string {
  if (isChinaImportStrategy(model.fulfillment.strategy)) {
    return "Complete China preparation and warehouse packing before confirming delivery to the customer agent.";
  }

  return "Complete warehouse preparation before confirming delivery to the customer agent.";
}

export function hasFulfillmentPermission(
  permissions: string[] | undefined,
  permission: string | undefined,
): boolean {
  if (!permission) {
    return true;
  }
  if (!permissions) {
    return true;
  }
  return permissions.includes(permission);
}

function isTerminalFulfillment(status: string): boolean {
  return TERMINAL_FULFILLMENT_STATUSES.has(status);
}

function isCustomerAgentDelivery(deliveryType?: string | null): boolean {
  return deliveryType === "customer_agent";
}

function isCompanyShippingDelivery(deliveryType?: string | null): boolean {
  return deliveryType != null && COMPANY_SHIPPING_DELIVERY_TYPES.has(deliveryType);
}

function isTanzaniaLocalDelivery(deliveryType?: string | null): boolean {
  return deliveryType === "self_pickup" || deliveryType === "negotiated_delivery";
}

function isExportReady(model: FulfillmentOperationalModel): boolean {
  return (
    model.china?.export_readiness === "export_ready" ||
    Boolean(model.china?.export_ready_at)
  );
}

/** Warehouse statuses to apply in order when completing China packing in one action. */
export function resolveChinaPackingAdvanceStatuses(currentStatus: string): string[] {
  const statuses: string[] = [];
  let current = currentStatus.toLowerCase();

  while (current !== "packed") {
    const next = WAREHOUSE_FORWARD[current];
    if (!next || next === "ready_to_ship") {
      break;
    }
    statuses.push(next);
    current = next;
  }

  return statuses;
}

/** Warehouse statuses to apply in order when marking a TZ local order ready in one action. */
export function resolveLocalReadyAdvanceStatuses(currentStatus: string): string[] {
  const statuses: string[] = [];
  let current = currentStatus.toLowerCase();

  while (current !== "ready_to_ship") {
    const next = WAREHOUSE_FORWARD[current];
    if (!next) {
      break;
    }
    statuses.push(next);
    current = next;
  }

  return statuses;
}

function isLocalWarehouseShortcutEligible(
  model: FulfillmentOperationalModel,
  currentStatus: string,
): boolean {
  const { fulfillment, order, warehouse } = model;

  if (isChinaImportStrategy(fulfillment.strategy)) {
    return false;
  }

  if (isCustomerAgentDelivery(order?.delivery_type)) {
    return false;
  }

  if (!isTanzaniaLocalDelivery(order?.delivery_type)) {
    return false;
  }

  return Boolean(warehouse && LOCAL_PRE_READY_WAREHOUSE_STATUSES.has(currentStatus));
}

function hasLocalWarehouseReadyPermissions(permissions?: string[]): boolean {
  return (
    hasFulfillmentPermission(permissions, "warehouse.jobs.update") &&
    hasFulfillmentPermission(permissions, "warehouse.jobs.complete")
  );
}

function resolvePendingPurchaseOrder(
  model: FulfillmentOperationalModel,
  purchaseOrders?: FulfillmentActionPurchaseOrder[],
): FulfillmentActionPurchaseOrder | null {
  if (purchaseOrders?.length) {
    const pending = purchaseOrders.find((po) => {
      const response = (po.supplier_response ?? "pending").toLowerCase();
      return response === "pending";
    });
    if (pending) {
      return pending;
    }
  }

  const procurementPending = model.china?.procurement?.find((row) => {
    const response = (row.supplier_response ?? "pending").toLowerCase();
    return response === "pending";
  });

  if (!procurementPending) {
    return null;
  }

  return {
    id: "",
    status: procurementPending.status ?? null,
    supplier_response: procurementPending.supplier_response ?? "pending",
    purchase_number: procurementPending.purchase_number ?? null,
  };
}

function resolveReceivablePurchaseOrder(
  purchaseOrders?: FulfillmentActionPurchaseOrder[],
): FulfillmentActionPurchaseOrder | null {
  if (!purchaseOrders?.length) {
    return null;
  }

  return (
    purchaseOrders.find((po) => {
      const status = (po.status ?? "").toLowerCase();
      const response = (po.supplier_response ?? "pending").toLowerCase();
      return (
        ["confirmed", "partially_received"].includes(status) &&
        ["accepted", "partially_accepted"].includes(response)
      );
    }) ?? null
  );
}

function pushAction(
  actions: FulfillmentAvailableAction[],
  action: FulfillmentAvailableAction,
  permissions?: string[],
): void {
  if (!hasFulfillmentPermission(permissions, action.permission)) {
    return;
  }
  actions.push(action);
}

function resolveChinaActions(
  model: FulfillmentOperationalModel,
  permissions?: string[],
  purchaseOrders?: FulfillmentActionPurchaseOrder[],
): FulfillmentAvailableAction[] {
  const actions: FulfillmentAvailableAction[] = [];
  const { fulfillment, order, china } = model;

  if (!isChinaImportStrategy(fulfillment.strategy) || isTerminalFulfillment(fulfillment.status)) {
    return actions;
  }

  const procurement = china?.procurement ?? [];
  const hasProcurement = procurement.length > 0;
  const stage = (china?.stage ?? "").toLowerCase();

  if (!hasProcurement && (china == null || stage === "" || stage === "awaiting_procurement")) {
    pushAction(
      actions,
      {
        key: "CREATE_PURCHASE",
        label: "Create supplier purchase",
        description: "Start supplier procurement via China workflow bootstrap.",
        requires_confirmation: true,
        confirmation_title: "Create supplier purchase?",
        confirmation_message:
          "This bootstraps the China workflow and creates purchase orders for this fulfilment.",
        available: Boolean(order?.id),
        unavailable_reason: order?.id ? undefined : "Order id is required.",
        permission: "procurement.create",
      },
      permissions,
    );
  }

  const pendingPo = resolvePendingPurchaseOrder(model, purchaseOrders);
  const waitingSupplier =
    pendingPo &&
    ["sent", "draft", "confirmed"].includes((pendingPo.status ?? "").toLowerCase());

  if (
    hasProcurement &&
    waitingSupplier &&
    ["procurement_in_progress", "partially_received", "awaiting_procurement"].includes(stage)
  ) {
    pushAction(
      actions,
      {
        key: "CONFIRM_PURCHASE",
        label: "Confirm supplier purchase",
        description: "Record supplier acceptance for the pending purchase order.",
        requires_confirmation: true,
        confirmation_title: "Confirm supplier purchase?",
        confirmation_message:
          "This records supplier acceptance and advances procurement in the China workflow.",
        available: Boolean(pendingPo?.id || purchaseOrders?.length),
        unavailable_reason:
          pendingPo?.id || purchaseOrders?.length
            ? undefined
            : "Purchase order id is required to confirm supplier response.",
        permission: "procurement.update",
        meta: pendingPo?.id ? { purchase_order_id: pendingPo.id } : undefined,
      },
      permissions,
    );
  }

  const receivablePo = resolveReceivablePurchaseOrder(purchaseOrders);
  if (
    hasProcurement &&
    receivablePo &&
    ["procurement_in_progress", "partially_received"].includes(stage)
  ) {
    const poLabel = receivablePo.purchase_number ?? receivablePo.id;
    pushAction(
      actions,
      {
        key: "RECEIVE_GOODS",
        label: "Receive goods",
        description: "Receive all outstanding quantities for the confirmed purchase order.",
        requires_confirmation: true,
        confirmation_title: "Receive goods?",
        confirmation_message: `This records receipt of all outstanding items for purchase order ${poLabel} and advances the China workflow toward QC.`,
        available: Boolean(receivablePo.id),
        unavailable_reason: receivablePo.id
          ? undefined
          : "Purchase order id is required to receive goods.",
        permission: "purchase_orders.receive",
        meta: receivablePo.id
          ? {
              purchase_order_id: receivablePo.id,
              purchase_number: receivablePo.purchase_number ?? null,
            }
          : undefined,
      },
      permissions,
    );
  }

  const qcPending =
    (china?.qc_status ?? "pending").toLowerCase() === "pending" &&
    ["received", "partially_received", "qc_pending"].includes(stage);

  if (qcPending) {
    pushAction(
      actions,
      {
        key: "START_QC",
        label: "Complete QC",
        description: "Record quality inspection as passed for goods received in China.",
        requires_confirmation: false,
        available: Boolean(order?.id),
        permission: "procurement.update",
        meta: { qc_status: "passed" },
      },
      permissions,
    );
  }

  const exportNotReady = !isExportReady(model);
  const exportReadyEligible = isEligibleForMarkExportReady({
    strategy: fulfillment.strategy,
    status: fulfillment.status,
    delivery_type: order?.delivery_type,
    warehouse_status: model.warehouse?.status,
    china: model.china,
  });

  if (
    !isCustomerAgentDelivery(order?.delivery_type) &&
    exportNotReady &&
    exportReadyEligible
  ) {
    pushAction(
      actions,
      {
        key: "MARK_EXPORT_READY",
        label: "Mark export ready",
        description: "Confirm export documentation checklist and mark goods export ready.",
        requires_confirmation: true,
        confirmation_title: "Mark export ready?",
        confirmation_message:
          "This confirms export documents and weight/dimensions, then marks the order export ready for shipping.",
        available: Boolean(order?.id),
        permission: "warehouse.jobs.complete",
        meta: {
          commercial_invoice: true,
          packing_list: true,
          customs_docs: true,
          weight_confirmed: true,
          dimensions_confirmed: true,
        },
      },
      permissions,
    );
  }

  return actions;
}

function isCompanyHandoverReady(model: FulfillmentOperationalModel): boolean {
  const { fulfillment, order, shipment } = model;

  return (
    fulfillment.status === "shipped" &&
    Boolean(shipment?.arrived_at) &&
    Boolean(order?.last_mile_receiving_method)
  );
}

function resolveCompanyShippingActions(
  model: FulfillmentOperationalModel,
  permissions?: string[],
): FulfillmentAvailableAction[] {
  const actions: FulfillmentAvailableAction[] = [];
  const { fulfillment, order, warehouse, shipment } = model;

  if (
    isTerminalFulfillment(fulfillment.status) ||
    !isCompanyShippingDelivery(order?.delivery_type) ||
    isCustomerAgentDelivery(order?.delivery_type) ||
    !isChinaImportStrategy(fulfillment.strategy)
  ) {
    return actions;
  }

  const warehouseReady = (warehouse?.status ?? "").toLowerCase() === "ready_to_ship";
  const exportReadyForShipment =
    !isChinaImportStrategy(fulfillment.strategy) || isExportReady(model);

  if (
    !shipment &&
    fulfillment.status === "ready_for_shipping" &&
    warehouseReady &&
    exportReadyForShipment
  ) {
    pushAction(
      actions,
      {
        key: "CREATE_SHIPMENT",
        label: "Create shipment",
        description: "Create a company shipment for this fulfilment.",
        requires_confirmation: true,
        confirmation_title: "Create shipment?",
        confirmation_message:
          "This creates a shipment and moves the order into the shipping workflow.",
        available: true,
        permission: "orders.ship",
      },
      permissions,
    );
  }

  if (
    shipment &&
    fulfillment.status === "ready_for_shipping" &&
    !["in_transit", "delivered"].includes((shipment.status ?? "").toLowerCase())
  ) {
    pushAction(
      actions,
      {
        key: "DISPATCH_SHIPMENT",
        label: "Dispatch shipment",
        description: "Record departure from origin to move the shipment in transit.",
        requires_confirmation: true,
        confirmation_title: "Dispatch shipment?",
        confirmation_message:
          "This records an in-transit tracking event and advances fulfilment to shipped.",
        available: true,
        permission: "orders.ship",
        meta: {
          shipment_id: shipment.id,
          event_type: "departed_origin",
        },
      },
      permissions,
    );
  }

  if (
    shipment &&
    fulfillment.status === "shipped" &&
    !shipment.arrived_at &&
    isCompanyShippingDelivery(order?.delivery_type)
  ) {
    pushAction(
      actions,
      {
        key: "CONFIRM_ARRIVED_TANZANIA",
        label: "Confirm Arrived Tanzania",
        description: "Record Tanzania arrival and notify the customer to choose pickup or delivery.",
        requires_confirmation: true,
        confirmation_title: "Confirm arrived in Tanzania?",
        confirmation_message:
          "This records an arrival tracking event, notifies the customer, and opens the last-mile receiving choice.",
        available: true,
        permission: "orders.ship",
        meta: {
          shipment_id: shipment.id,
          event_type: "arrived_destination",
        },
      },
      permissions,
    );
  }

  if (isCompanyHandoverReady(model)) {
    const receivingMethod = (order?.last_mile_receiving_method ?? "").toLowerCase();

    if (receivingMethod === "self_pickup") {
      pushAction(
        actions,
        {
          key: "MARK_CUSTOMER_COLLECTED",
          label: "Confirm customer collected",
          description: "Confirm the customer has collected this order and complete fulfilment.",
          requires_confirmation: true,
          confirmation_title: "Confirm customer collected?",
          confirmation_message:
            "This confirms the customer has collected the order and closes the China company shipping journey.",
          available: true,
          permission: "orders.fulfill",
        },
        permissions,
      );
    }

    if (receivingMethod === "negotiated_delivery") {
      pushAction(
        actions,
        {
          key: "MARK_CUSTOMER_DELIVERED",
          label: "Confirm customer delivered",
          description: "Confirm the customer has received this order and complete fulfilment.",
          requires_confirmation: true,
          confirmation_title: "Confirm customer delivered?",
          confirmation_message:
            "This confirms the customer has received the order and closes the China company shipping journey.",
          available: true,
          permission: "orders.fulfill",
        },
        permissions,
      );
    }
  }

  if (
    shipment &&
    fulfillment.status === "shipped" &&
    (shipment.status ?? "").toLowerCase() !== "delivered" &&
    !isCompanyShippingDelivery(order?.delivery_type) &&
    !isCompanyHandoverReady(model)
  ) {
    pushAction(
      actions,
      {
        key: "COMPLETE_DELIVERY",
        label: "Complete delivery",
        description: "Record a delivered tracking event to complete company shipping.",
        requires_confirmation: true,
        confirmation_title: "Complete delivery?",
        confirmation_message:
          "This records a delivered tracking event and reconciles fulfilment delivery status.",
        available: true,
        permission: "orders.ship",
        meta: {
          shipment_id: shipment.id,
          event_type: "delivered",
        },
      },
      permissions,
    );
  }

  return actions;
}

function resolveLocalCompletionActions(
  model: FulfillmentOperationalModel,
  permissions?: string[],
): FulfillmentAvailableAction[] {
  const actions: FulfillmentAvailableAction[] = [];
  const { fulfillment, order, warehouse } = model;

  if (
    isTerminalFulfillment(fulfillment.status) ||
    isChinaImportStrategy(fulfillment.strategy) ||
    isCustomerAgentDelivery(order?.delivery_type) ||
    !isTanzaniaLocalDelivery(order?.delivery_type)
  ) {
    return actions;
  }

  const warehouseReady = (warehouse?.status ?? "").toLowerCase() === "ready_to_ship";
  const orderReady = fulfillment.status === "ready_for_shipping";

  if (!warehouseReady || !orderReady) {
    return actions;
  }

  pushAction(
    actions,
    {
      key: "COMPLETE_LOCAL_ORDER",
      label: "Mark order completed",
      description: "Confirm that the customer has collected or received this order.",
      requires_confirmation: true,
      confirmation_title: "Mark order completed?",
      confirmation_message:
        "This confirms the customer has collected or received the order and closes the Buy From TZ fulfilment journey.",
      available: true,
      permission: "orders.fulfill",
    },
    permissions,
  );

  return actions;
}

function resolveWarehouseActions(
  model: FulfillmentOperationalModel,
  permissions?: string[],
): FulfillmentAvailableAction[] {
  const actions: FulfillmentAvailableAction[] = [];
  const { fulfillment, order, warehouse } = model;

  if (isTerminalFulfillment(fulfillment.status)) {
    return actions;
  }

  const isChina = isChinaImportStrategy(fulfillment.strategy);
  if (isChina) {
    const qcPassed = (model.china?.qc_status ?? "").toLowerCase() === "passed";
    const stage = (model.china?.stage ?? "").toLowerCase();
    if (!qcPassed || !CHINA_WAREHOUSE_STAGES.has(stage)) {
      return actions;
    }
  }

  const currentStatus = (warehouse?.status ?? "").toLowerCase();
  const nextStatus = WAREHOUSE_FORWARD[currentStatus] ?? null;
  const chinaUsesPackingShortcut =
    isChina &&
    warehouse &&
    CHINA_PRE_PACKED_WAREHOUSE_STATUSES.has(currentStatus);

  if (chinaUsesPackingShortcut) {
    pushAction(
      actions,
      {
        key: "COMPLETE_PACKING",
        label: "Complete packing",
        description:
          "Complete China warehouse preparation through to packed in one step.",
        requires_confirmation: true,
        confirmation_title: "Complete packing?",
        confirmation_message:
          "This advances warehouse preparation through to packed so the order can move to ready to ship.",
        available: true,
        permission: "warehouse.jobs.update",
        meta: {
          warehouse_job_id: warehouse.id,
          target_status: "packed",
        },
      },
      permissions,
    );
  } else if (isLocalWarehouseShortcutEligible(model, currentStatus) && warehouse) {
    if (hasLocalWarehouseReadyPermissions(permissions)) {
      actions.push({
        key: "MARK_LOCAL_ORDER_READY",
        label: "Mark order ready",
        description:
          "Complete order preparation and make the order ready for customer collection or delivery arrangement.",
        requires_confirmation: true,
        confirmation_title: "Mark order ready?",
        confirmation_message:
          "This completes warehouse preparation, marks the order ready, and notifies the customer based on their collection preference.",
        available: true,
        permission: "warehouse.jobs.complete",
        meta: {
          warehouse_job_id: warehouse.id,
          target_status: "ready_to_ship",
        },
      });
    }
  } else if (
    warehouse &&
    nextStatus &&
    !(isCustomerAgentDelivery(order?.delivery_type) && nextStatus === "ready_to_ship")
  ) {
    const isReadyToShipStep = nextStatus === "ready_to_ship";
    const isLocal = !isChina;
    const label = isReadyToShipStep
      ? isLocal
        ? "Mark order ready"
        : "Mark ready to ship"
      : "Advance packing";

    pushAction(
      actions,
      {
        key: "MARK_READY",
        label,
        description: isReadyToShipStep
          ? isLocal
            ? "Mark the order ready and notify the customer according to their collection preference."
            : "Mark warehouse job ready for shipping or agent delivery."
          : "Advance warehouse preparation to the next packing step.",
        requires_confirmation: isReadyToShipStep,
        confirmation_title: isReadyToShipStep
          ? isLocal
            ? "Mark order ready?"
            : "Mark ready to ship?"
          : undefined,
        confirmation_message: isReadyToShipStep
          ? isLocal
            ? "This marks the order ready and notifies the customer based on their collection preference."
            : "This marks the warehouse job ready and syncs fulfilment status."
          : undefined,
        available: true,
        permission: "warehouse.jobs.update",
        meta: {
          warehouse_job_id: warehouse.id,
          next_status: nextStatus,
        },
      },
      permissions,
    );
  }

  if (!isChina) {
    pushAction(
      actions,
      {
        key: "ASSIGN_DELIVERY",
        label: "Assign delivery",
        description: "Assign a delivery operator or route for local fulfilment.",
        requires_confirmation: false,
        available: false,
        unavailable_reason:
          "Delivery assignment is handled manually outside the system for Buy From TZ orders.",
        permission: "warehouse.jobs.update",
      },
      permissions,
    );
  }

  return actions;
}

function isAgentDeliveryComplete(customerAgent?: CustomerAgentOperationalState): boolean {
  const agent = customerAgent ?? null;
  const deliveryStatus = (agent?.pickup_status ?? "").toLowerCase();
  return Boolean(agent?.handover_completed_at) || deliveryStatus === "handover_completed";
}

function resolveCustomerAgentActions(
  model: FulfillmentOperationalModel,
  permissions?: string[],
  customerAgent?: CustomerAgentOperationalState,
): FulfillmentAvailableAction[] {
  const actions: FulfillmentAvailableAction[] = [];
  const { fulfillment, order } = model;

  if (!isCustomerAgentDelivery(order?.delivery_type) || isTerminalFulfillment(fulfillment.status)) {
    return actions;
  }

  const agent = customerAgent ?? null;
  const deliveryComplete = isAgentDeliveryComplete(agent);
  const sellerReady = isSellerReadyToConfirmAgentDelivery(model);

  if (!agent?.id) {
    pushAction(
      actions,
      {
        key: "AGENT_BOOTSTRAP",
        label: "Initialize agent delivery",
        description:
          "Create the seller workflow for delivering this order to the customer's nominated agent.",
        requires_confirmation: false,
        available: Boolean(order?.id),
        permission: "orders.ship",
      },
      permissions,
    );
    return actions;
  }

  if (!deliveryComplete) {
    pushAction(
      actions,
      {
        key: "MARK_AGENT_DELIVERED",
        label: "Deliver to customer agent",
        description:
          "Confirm that this order has been delivered to the customer's nominated agent.",
        requires_confirmation: true,
        confirmation_title: "Deliver to customer agent?",
        confirmation_message:
          "This confirms seller delivery to the customer's nominated agent and updates customer-visible progress.",
        available: sellerReady,
        unavailable_reason: sellerReady ? undefined : resolveAgentHandoverUnavailableReason(model),
        permission: "orders.ship",
      },
      permissions,
    );
  }

  return actions;
}

export function resolveFulfillmentAvailableActions(
  input: FulfillmentAvailableActionsInput,
): FulfillmentAvailableAction[] {
  const { model, permissions, customerAgent, purchaseOrders } = input;
  const deliveryType = model.order?.delivery_type ?? null;
  const isCustomerAgent = isCustomerAgentDelivery(deliveryType);

  const chinaActions = resolveChinaActions(model, permissions, purchaseOrders);
  const warehouseActions = resolveWarehouseActions(model, permissions);
  const localCompletionActions = resolveLocalCompletionActions(model, permissions);
  const shippingActions = isCustomerAgent
    ? []
    : resolveCompanyShippingActions(model, permissions);
  const agentActions = isCustomerAgent
    ? resolveCustomerAgentActions(model, permissions, customerAgent)
    : [];

  const merged = new Map<FulfillmentActionKey, FulfillmentAvailableAction>();
  for (const action of [
    ...chinaActions,
    ...warehouseActions,
    ...localCompletionActions,
    ...shippingActions,
    ...agentActions,
  ]) {
    if (!merged.has(action.key) || action.available) {
      merged.set(action.key, action);
    }
  }

  return Array.from(merged.values());
}

export function filterVisibleFulfillmentActions(
  actions: FulfillmentAvailableAction[],
): FulfillmentAvailableAction[] {
  return actions.filter((action) => action.available);
}

function resolveMarkReadyAction(
  actions: FulfillmentAvailableAction[],
  nextStatus: string,
): FulfillmentAvailableAction | undefined {
  const markReady = actions.find((action) => action.key === "MARK_READY" && action.available);
  if (!markReady) {
    return undefined;
  }
  const next = String(markReady.meta?.next_status ?? "");
  return next === nextStatus ? markReady : undefined;
}

function resolveUnavailableAgentDeliveryAction(
  actions: FulfillmentAvailableAction[],
): FulfillmentAvailableAction | null {
  return actions.find((action) => action.key === "MARK_AGENT_DELIVERED" && !action.available) ?? null;
}

export function selectPrimaryFulfillmentAction(
  actions: FulfillmentAvailableAction[],
): FulfillmentAvailableAction | null {
  const available = filterVisibleFulfillmentActions(actions);
  if (available.length === 0) {
    return resolveUnavailableAgentDeliveryAction(actions);
  }

  const byKey = new Map<FulfillmentAvailableAction["key"], FulfillmentAvailableAction>();
  for (const action of available) {
    byKey.set(action.key, action);
  }

  const ordered: Array<FulfillmentAvailableAction | undefined> = [
    byKey.get("CREATE_PURCHASE"),
    byKey.get("CONFIRM_PURCHASE"),
    byKey.get("RECEIVE_GOODS"),
    byKey.get("START_QC"),
    byKey.get("COMPLETE_PACKING"),
    byKey.get("MARK_LOCAL_ORDER_READY"),
    resolveMarkReadyAction(available, "ready_to_ship"),
    resolveMarkReadyAction(available, "picking"),
    resolveMarkReadyAction(available, "picked"),
    resolveMarkReadyAction(available, "packing"),
    resolveMarkReadyAction(available, "packed"),
    byKey.get("MARK_EXPORT_READY"),
    byKey.get("CREATE_SHIPMENT"),
    byKey.get("DISPATCH_SHIPMENT"),
    byKey.get("CONFIRM_ARRIVED_TANZANIA"),
    byKey.get("MARK_CUSTOMER_COLLECTED"),
    byKey.get("MARK_CUSTOMER_DELIVERED"),
    byKey.get("COMPLETE_DELIVERY"),
    byKey.get("COMPLETE_LOCAL_ORDER"),
  ];

  for (const key of AGENT_ACTION_PRIORITY) {
    ordered.push(byKey.get(key));
  }

  for (const action of ordered) {
    if (action?.available) {
      return action;
    }
  }

  return available[0] ?? null;
}

export function shouldShowNextActionsPanel(actions: FulfillmentAvailableAction[]): boolean {
  return selectPrimaryFulfillmentAction(actions) !== null;
}

export function resolveActionConfirmationCopy(action: FulfillmentAvailableAction): {
  title: string;
  message: string;
} | null {
  if (!action.requires_confirmation) {
    return null;
  }

  return {
    title: action.confirmation_title ?? `${action.label}?`,
    message:
      action.confirmation_message ??
      action.description ??
      "This will update fulfilment workflow state.",
  };
}

const ACTION_IMPACT: Partial<Record<FulfillmentActionKey, string>> = {
  CREATE_PURCHASE: "Starts China procurement and supplier purchase orders.",
  CONFIRM_PURCHASE: "Advances supplier procurement and downstream China workflow.",
  RECEIVE_GOODS: "Records goods receipt and unlocks QC in the China workflow.",
  START_QC: "Records QC completion and unlocks warehouse preparation.",
  COMPLETE_PACKING: "Completes China warehouse preparation through to packed.",
  MARK_LOCAL_ORDER_READY:
    "Completes warehouse preparation, marks the order ready, and notifies the customer.",
  MARK_EXPORT_READY: "Marks goods export-ready for shipment eligibility.",
  CREATE_SHIPMENT: "Generates shipment and starts logistics tracking for the customer.",
  DISPATCH_SHIPMENT: "Moves shipment in transit and updates customer shipping progress.",
  CONFIRM_ARRIVED_TANZANIA:
    "Records Tanzania arrival, notifies the customer, and opens last-mile receiving choice.",
  MARK_CUSTOMER_COLLECTED: "Confirms customer collection and completes China company shipping fulfilment.",
  MARK_CUSTOMER_DELIVERED: "Confirms customer delivery and completes China company shipping fulfilment.",
  MARK_READY: "Completes warehouse prep and moves fulfilment toward shipping.",
  ASSIGN_DELIVERY: "Assigns delivery handling within the shipping workflow.",
  COMPLETE_DELIVERY: "Confirms delivery and closes the fulfilment journey.",
  COMPLETE_LOCAL_ORDER: "Updates order completion status and customer tracking.",
  AGENT_BOOTSTRAP: "Prepares seller delivery to the customer's nominated agent.",
  MARK_AGENT_DELIVERED: MARK_AGENT_DELIVERED_IMPACT,
};

export function resolveActionImpact(action: FulfillmentAvailableAction): string {
  return (
    ACTION_IMPACT[action.key] ??
    "Updates operational state and may change customer-visible order progress."
  );
}
