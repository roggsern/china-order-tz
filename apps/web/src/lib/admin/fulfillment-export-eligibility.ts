import type { AdminFulfillmentChinaSummary } from "@/lib/api/admin-fulfillments";

const TERMINAL_FULFILLMENT_STATUSES = new Set(["delivered", "cancelled"]);

export const MARK_EXPORT_READY_WORKFLOW_STAGES = new Set([
  "qc_passed",
  "consolidated",
  "consolidating",
  "received",
]);

export type MarkExportReadyEligibilityInput = {
  strategy: string;
  status: string;
  delivery_type?: string | null;
  warehouse_status?: string | null;
  china?: Pick<
    AdminFulfillmentChinaSummary,
    "stage" | "qc_status" | "export_ready"
  > | null;
};

export function isChinaExportReadyFromSummary(
  china?: Pick<AdminFulfillmentChinaSummary, "export_ready"> | null,
): boolean {
  return china?.export_ready === true;
}

/** Mirrors single-order MARK_EXPORT_READY availability in fulfillment-available-actions. */
export function isEligibleForMarkExportReady(input: MarkExportReadyEligibilityInput): boolean {
  if (input.strategy !== "china") {
    return false;
  }

  if (TERMINAL_FULFILLMENT_STATUSES.has(input.status)) {
    return false;
  }

  const deliveryType = (input.delivery_type ?? "").toLowerCase();
  if (deliveryType === "customer_agent" || deliveryType !== "company_shipping") {
    return false;
  }

  if (isChinaExportReadyFromSummary(input.china)) {
    return false;
  }

  const qcPassed = (input.china?.qc_status ?? "").toLowerCase() === "passed";
  if (!qcPassed) {
    return false;
  }

  const stage = (input.china?.stage ?? "").toLowerCase();
  if (!MARK_EXPORT_READY_WORKFLOW_STAGES.has(stage)) {
    return false;
  }

  const warehouseStatus = (input.warehouse_status ?? "").toLowerCase();
  if (warehouseStatus !== "ready_to_ship") {
    return false;
  }

  return true;
}
