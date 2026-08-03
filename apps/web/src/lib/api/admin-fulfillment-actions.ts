import type { FulfillmentAvailableAction } from "@/lib/admin/fulfillment-available-actions";
import {
  resolveChinaPackingAdvanceStatuses,
  resolveLocalReadyAdvanceStatuses,
} from "@/lib/admin/fulfillment-available-actions";
import type { FulfillmentOperationalModel } from "@/lib/admin/fulfillment-operational";
import { createAdminShipment, postAdminTrackingEvent } from "@/lib/api/admin-shipments";
import {
  fetchAdminPurchaseOrder,
  receiveAdminPurchaseOrder,
} from "@/lib/api/admin-procurement";
import { updateAdminWarehouseStatus } from "@/lib/api/admin-warehouse";

export class AdminFulfillmentActionError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "AdminFulfillmentActionError";
  }
}

type ApiPayload = {
  success?: boolean;
  message?: string;
  errors?: Record<string, string[]>;
};

async function parseJson<T>(response: Response): Promise<T> {
  try {
    return (await response.json()) as T;
  } catch {
    return {} as T;
  }
}

function throwFromPayload(response: Response, payload: ApiPayload, fallback: string): never {
  const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
  throw new AdminFulfillmentActionError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

async function postAdminAction(path: string, body?: Record<string, unknown>): Promise<unknown> {
  const response = await fetch(path, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: body ? JSON.stringify(body) : undefined,
    cache: "no-store",
  });
  const payload = await parseJson<ApiPayload & { data?: unknown }>(response);
  if (!response.ok || payload.success === false) {
    throwFromPayload(response, payload, "Unable to complete fulfilment action.");
  }
  return payload.data ?? payload;
}

export type AdminCustomerAgentPickup = {
  id: string;
  authorization_status?: string | null;
  release_status?: string | null;
  pickup_status?: string | null;
  handover_completed_at?: string | null;
};

export async function fetchAdminCustomerAgentPickup(
  orderId: string,
): Promise<AdminCustomerAgentPickup | null> {
  const response = await fetch(`/api/admin/orders/${encodeURIComponent(orderId)}/customer-agent`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<{ success?: boolean; data?: AdminCustomerAgentPickup | null }>(
    response,
  );
  if (!response.ok || payload.success === false) {
    throw new AdminFulfillmentActionError(
      "Unable to load customer agent delivery state.",
      response.status,
    );
  }
  return payload.data ?? null;
}

export function sanitizeCustomerAgentDeliveryError(message: string): string {
  return message
    .replace(
      /Fulfillment must be ready_for_shipping before Customer Agent pickup\./gi,
      "Complete fulfilment preparation before confirming delivery to the customer agent.",
    )
    .replace(
      /Warehouse must be ready_to_ship before Customer Agent pickup\./gi,
      "Warehouse preparation must be complete before confirming delivery to the customer agent.",
    )
    .replace(
      /China export readiness is required before Customer Agent pickup\./gi,
      "China export preparation must be complete before confirming delivery to the customer agent.",
    )
    .replace(
      /Valid pickup authorization is required before Customer Agent pickup\./gi,
      "Agent delivery prerequisites are not yet met.",
    )
    .replace(/Customer Agent pickup/gi, "Customer agent delivery")
    .replace(/pickup authorization/gi, "agent delivery authorization")
    .replace(/\bpickup\b/gi, "delivery");
}

async function ensureCustomerAgentAuthorized(orderId: string): Promise<void> {
  const agent = await fetchAdminCustomerAgentPickup(orderId);
  const authorization = (agent?.authorization_status ?? "pending").toLowerCase();

  if (authorization === "authorized") {
    return;
  }

  if (["rejected", "revoked"].includes(authorization)) {
    await postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/customer-agent/authorize`, {
      reissue: true,
    });
    return;
  }

  await postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/customer-agent/authorize`);
}

async function confirmCustomerAgentDelivery(orderId: string): Promise<unknown> {
  await ensureCustomerAgentAuthorized(orderId);
  return postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/customer-agent/handover`);
}

export async function fetchAdminChinaWorkflowPurchaseOrders(
  orderId: string,
): Promise<
  Array<{
    id: string;
    status?: string;
    supplier_response?: string | null;
    purchase_number?: string | null;
  }>
> {
  const response = await fetch(`/api/admin/orders/${encodeURIComponent(orderId)}/china-workflow`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    data?: {
      purchase_orders?: Array<{
        id: string;
        status?: string;
        supplier_response?: string | null;
        purchase_number?: string | null;
      }>;
    };
  }>(response);
  if (!response.ok || payload.success === false) {
    throw new AdminFulfillmentActionError("Unable to load China workflow purchase orders.", response.status);
  }
  return payload.data?.purchase_orders ?? [];
}

async function resolveReceivablePurchaseOrderId(
  model: FulfillmentOperationalModel,
  action: FulfillmentAvailableAction,
): Promise<string> {
  const existing = action.meta?.purchase_order_id;
  if (typeof existing === "string" && existing.length > 0) {
    return existing;
  }
  if (!model.order?.id) {
    throw new AdminFulfillmentActionError("Order id is required.");
  }
  const purchaseOrders = await fetchAdminChinaWorkflowPurchaseOrders(model.order.id);
  const receivable = purchaseOrders.find((po) => {
    const status = (po.status ?? "").toLowerCase();
    const response = (po.supplier_response ?? "pending").toLowerCase();
    return (
      ["confirmed", "partially_received"].includes(status) &&
      ["accepted", "partially_accepted"].includes(response)
    );
  });
  if (!receivable?.id) {
    throw new AdminFulfillmentActionError("No receivable purchase order found for goods receipt.");
  }
  return receivable.id;
}

async function resolvePurchaseOrderId(
  model: FulfillmentOperationalModel,
  action: FulfillmentAvailableAction,
): Promise<string> {
  const existing = action.meta?.purchase_order_id;
  if (typeof existing === "string" && existing.length > 0) {
    return existing;
  }
  if (!model.order?.id) {
    throw new AdminFulfillmentActionError("Order id is required.");
  }
  const purchaseOrders = await fetchAdminChinaWorkflowPurchaseOrders(model.order.id);
  const pending = purchaseOrders.find((po) => {
    const response = (po.supplier_response ?? "pending").toLowerCase();
    return response === "pending";
  });
  if (!pending?.id) {
    throw new AdminFulfillmentActionError("No pending purchase order found for supplier confirmation.");
  }
  return pending.id;
}

export async function executeFulfillmentAction(
  model: FulfillmentOperationalModel,
  action: FulfillmentAvailableAction,
): Promise<unknown> {
  if (!action.available) {
    throw new AdminFulfillmentActionError(
      action.unavailable_reason ?? "This action is not available for the current fulfilment state.",
    );
  }

  const orderId = model.order?.id;
  const fulfillmentId = model.fulfillment.id;

  switch (action.key) {
    case "CREATE_PURCHASE":
      if (!orderId) {
        throw new AdminFulfillmentActionError("Order id is required.");
      }
      return postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/china-workflow/bootstrap`);

    case "CONFIRM_PURCHASE": {
      const purchaseOrderId = await resolvePurchaseOrderId(model, action);
      return postAdminAction(
        `/api/admin/purchase-orders/${encodeURIComponent(purchaseOrderId)}/supplier-response`,
        { response: "accepted" },
      );
    }

    case "RECEIVE_GOODS": {
      const purchaseOrderId = await resolveReceivablePurchaseOrderId(model, action);
      const detail = await fetchAdminPurchaseOrder(purchaseOrderId);
      const items = (detail.items ?? [])
        .filter((item) => item.quantity_outstanding > 0)
        .map((item) => ({
          purchase_order_item_id: item.id,
          quantity: item.quantity_outstanding,
        }));

      if (items.length === 0) {
        throw new AdminFulfillmentActionError("No outstanding quantities to receive for this purchase order.");
      }

      return receiveAdminPurchaseOrder(purchaseOrderId, { items });
    }

    case "START_QC":
      if (!orderId) {
        throw new AdminFulfillmentActionError("Order id is required.");
      }
      return postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/china-workflow/qc`, {
        status: String(action.meta?.qc_status ?? "passed"),
      });

    case "MARK_EXPORT_READY":
      if (!orderId) {
        throw new AdminFulfillmentActionError("Order id is required.");
      }
      return postAdminAction(
        `/api/admin/orders/${encodeURIComponent(orderId)}/china-workflow/export-ready`,
        {
          commercial_invoice: true,
          packing_list: true,
          customs_docs: true,
          weight_confirmed: true,
          dimensions_confirmed: true,
        },
      );

    case "CREATE_SHIPMENT":
      return createAdminShipment(fulfillmentId);

    case "DISPATCH_SHIPMENT": {
      const shipmentId = String(action.meta?.shipment_id ?? model.shipment?.id ?? "");
      if (!shipmentId) {
        throw new AdminFulfillmentActionError("Shipment id is required.");
      }
      return postAdminTrackingEvent(shipmentId, {
        event_type: String(action.meta?.event_type ?? "departed_origin"),
      });
    }

    case "CONFIRM_ARRIVED_TANZANIA": {
      const shipmentId = String(action.meta?.shipment_id ?? model.shipment?.id ?? "");
      if (!shipmentId) {
        throw new AdminFulfillmentActionError("Shipment id is required.");
      }
      return postAdminTrackingEvent(shipmentId, {
        event_type: String(action.meta?.event_type ?? "arrived_destination"),
      });
    }

    case "COMPLETE_DELIVERY": {
      const shipmentId = String(action.meta?.shipment_id ?? model.shipment?.id ?? "");
      if (!shipmentId) {
        throw new AdminFulfillmentActionError("Shipment id is required.");
      }
      return postAdminTrackingEvent(shipmentId, {
        event_type: String(action.meta?.event_type ?? "delivered"),
      });
    }

    case "MARK_READY": {
      const warehouseJobId = String(action.meta?.warehouse_job_id ?? model.warehouse?.id ?? "");
      const nextStatus = String(action.meta?.next_status ?? "");
      if (!warehouseJobId || !nextStatus) {
        throw new AdminFulfillmentActionError("Warehouse job and next status are required.");
      }
      return updateAdminWarehouseStatus(warehouseJobId, { status: nextStatus });
    }

    case "COMPLETE_PACKING": {
      const warehouseJobId = String(action.meta?.warehouse_job_id ?? model.warehouse?.id ?? "");
      const currentStatus = (model.warehouse?.status ?? "").toLowerCase();
      if (!warehouseJobId) {
        throw new AdminFulfillmentActionError("Warehouse job is required.");
      }

      const advanceStatuses = resolveChinaPackingAdvanceStatuses(currentStatus);
      if (advanceStatuses.length === 0) {
        throw new AdminFulfillmentActionError("Warehouse packing is already complete.");
      }

      let lastResult: unknown;
      for (const status of advanceStatuses) {
        lastResult = await updateAdminWarehouseStatus(warehouseJobId, { status });
      }
      return lastResult;
    }

    case "MARK_LOCAL_ORDER_READY": {
      const warehouseJobId = String(action.meta?.warehouse_job_id ?? model.warehouse?.id ?? "");
      const currentStatus = (model.warehouse?.status ?? "").toLowerCase();
      if (!warehouseJobId) {
        throw new AdminFulfillmentActionError("Warehouse job is required.");
      }

      const advanceStatuses = resolveLocalReadyAdvanceStatuses(currentStatus);
      if (advanceStatuses.length === 0) {
        throw new AdminFulfillmentActionError("Order is already marked ready.");
      }

      let lastResult: unknown;
      for (const status of advanceStatuses) {
        lastResult = await updateAdminWarehouseStatus(warehouseJobId, { status });
      }
      return lastResult;
    }

    case "ASSIGN_DELIVERY":
      throw new AdminFulfillmentActionError(
        "Assign delivery is not supported — no backend endpoint exists.",
      );

    case "COMPLETE_LOCAL_ORDER":
      return postAdminAction(
        `/api/admin/fulfillments/${encodeURIComponent(fulfillmentId)}/complete-local`,
      );

    case "MARK_CUSTOMER_COLLECTED":
      return postAdminAction(
        `/api/admin/fulfillments/${encodeURIComponent(fulfillmentId)}/complete-company-handover-pickup`,
      );

    case "MARK_CUSTOMER_DELIVERED":
      return postAdminAction(
        `/api/admin/fulfillments/${encodeURIComponent(fulfillmentId)}/complete-company-handover-delivery`,
      );

    case "AGENT_BOOTSTRAP":
      if (!orderId) {
        throw new AdminFulfillmentActionError("Order id is required.");
      }
      return postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/customer-agent`);

    case "MARK_AGENT_DELIVERED":
    case "AGENT_HANDOVER":
      if (!orderId) {
        throw new AdminFulfillmentActionError("Order id is required.");
      }
      try {
        return await confirmCustomerAgentDelivery(orderId);
      } catch (error) {
        if (error instanceof AdminFulfillmentActionError) {
          throw new AdminFulfillmentActionError(
            sanitizeCustomerAgentDeliveryError(error.message),
            error.statusCode,
          );
        }
        throw error;
      }

    case "AGENT_AUTHORIZE":
      if (!orderId) {
        throw new AdminFulfillmentActionError("Order id is required.");
      }
      return postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/customer-agent/authorize`);

    case "AGENT_SCHEDULE":
      if (!orderId) {
        throw new AdminFulfillmentActionError("Order id is required.");
      }
      return postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/customer-agent/schedule`);

    case "AGENT_RELEASE":
      if (!orderId) {
        throw new AdminFulfillmentActionError("Order id is required.");
      }
      return postAdminAction(`/api/admin/orders/${encodeURIComponent(orderId)}/customer-agent/release`, {
        status: String(action.meta?.release_status ?? "released"),
      });

    default:
      throw new AdminFulfillmentActionError("Unsupported fulfilment action.");
  }
}
