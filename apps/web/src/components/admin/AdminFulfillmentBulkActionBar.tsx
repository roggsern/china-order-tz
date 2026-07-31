"use client";

import { useMemo, useState } from "react";
import {
  buildBulkCreateSupplierPurchaseConfirmationMessage,
  buildBulkLocalCompletedConfirmationMessage,
  buildBulkLocalReadyConfirmationMessage,
  buildBulkMarkChinaPackingCompleteConfirmationMessage,
  buildBulkMarkExportReadyConfirmationMessage,
  buildBulkMarkAgentDeliveredConfirmationMessage,
  buildBulkMarkCustomerCollectedConfirmationMessage,
  buildBulkMarkCustomerDeliveredConfirmationMessage,
  buildBulkCreateShipmentConfirmationMessage,
  buildBulkMarkQcPassedConfirmationMessage,
  buildBulkReceiveGoodsConfirmationMessage,
  buildLargeBulkSelectionWarning,
  countEligibleForBulkCreateSupplierPurchase,
  countEligibleForBulkLocalCompleted,
  countEligibleForBulkLocalReady,
  countEligibleForBulkMarkChinaPackingComplete,
  countEligibleForBulkMarkExportReady,
  countEligibleForBulkMarkAgentDelivered,
  countEligibleForBulkMarkCustomerCollected,
  countEligibleForBulkMarkCustomerDelivered,
  countEligibleForBulkCreateShipment,
  countEligibleForBulkMarkQcPassed,
  countEligibleForBulkReceiveGoods,
  groupBulkResultReasons,
  resolveBulkSuccessLabel,
  resolveVisibleBulkActionsForSelection,
  shouldClearBulkSelectionAfterSuccess,
  shouldWarnLargeBulkSelection,
  type BulkFulfillmentActionResponse,
  type BulkSelectionContext,
  type FulfillmentBulkActionDefinition,
  type FulfillmentBulkActionKey,
} from "@/lib/admin/fulfillment-bulk";
import {
  AdminFulfillmentBulkApiError,
  executeBulkFulfillmentAction,
} from "@/lib/api/admin-fulfillment-bulk";

type AdminFulfillmentBulkActionBarProps = {
  selectedCount: number;
  selectedRows: BulkSelectionContext[];
  permissions?: string[];
  onClearSelection: () => void;
  onCompleted?: (result: BulkFulfillmentActionResponse) => void;
};

function buildBulkEligibleHints(
  selectedCount: number,
  selectedRows: BulkSelectionContext[],
  availableActions: FulfillmentBulkActionDefinition[],
): string[] {
  const hints: string[] = [];

  if (availableActions.some((action) => action.key === "MARK_LOCAL_ORDER_READY")) {
    const eligibleCount = countEligibleForBulkLocalReady(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for Buy From TZ bulk ready action`);
    }
  }

  if (availableActions.some((action) => action.key === "MARK_LOCAL_ORDER_COMPLETED")) {
    const eligibleCount = countEligibleForBulkLocalCompleted(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for Buy From TZ bulk completion`);
    }
  }

  if (availableActions.some((action) => action.key === "CREATE_SUPPLIER_PURCHASE")) {
    const eligibleCount = countEligibleForBulkCreateSupplierPurchase(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for China supplier purchase creation`);
    }
  }

  if (availableActions.some((action) => action.key === "RECEIVE_GOODS")) {
    const eligibleCount = countEligibleForBulkReceiveGoods(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for China goods receipt`);
    }
  }

  if (availableActions.some((action) => action.key === "MARK_QC_PASSED")) {
    const eligibleCount = countEligibleForBulkMarkQcPassed(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for China QC pass`);
    }
  }

  if (availableActions.some((action) => action.key === "MARK_CHINA_PACKING_COMPLETE")) {
    const eligibleCount = countEligibleForBulkMarkChinaPackingComplete(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for China packing completion`);
    }
  }

  if (availableActions.some((action) => action.key === "MARK_EXPORT_READY")) {
    const eligibleCount = countEligibleForBulkMarkExportReady(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for China export readiness`);
    }
  }

  if (availableActions.some((action) => action.key === "MARK_AGENT_DELIVERED")) {
    const eligibleCount = countEligibleForBulkMarkAgentDelivered(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for customer agent delivery`);
    }
  }

  if (availableActions.some((action) => action.key === "CREATE_SHIPMENT")) {
    const eligibleCount = countEligibleForBulkCreateShipment(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for shipment creation`);
    }
  }

  if (availableActions.some((action) => action.key === "MARK_CUSTOMER_COLLECTED")) {
    const eligibleCount = countEligibleForBulkMarkCustomerCollected(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for customer collection confirmation`);
    }
  }

  if (availableActions.some((action) => action.key === "MARK_CUSTOMER_DELIVERED")) {
    const eligibleCount = countEligibleForBulkMarkCustomerDelivered(selectedRows);
    if (eligibleCount !== selectedCount) {
      hints.push(`${eligibleCount} eligible for customer delivery confirmation`);
    }
  }

  return hints;
}

function resolveConfirmationMessage(
  action: FulfillmentBulkActionDefinition,
  selectedCount: number,
  selectedRows: BulkSelectionContext[],
  eligibleReadyCount: number,
  eligibleCompletedCount: number,
  eligibleCreatePurchaseCount: number,
  eligibleReceiveGoodsCount: number,
  eligibleMarkQcPassedCount: number,
  eligibleMarkChinaPackingCompleteCount: number,
  eligibleMarkExportReadyCount: number,
  eligibleMarkAgentDeliveredCount: number,
  eligibleCreateShipmentCount: number,
  eligibleMarkCustomerCollectedCount: number,
  eligibleMarkCustomerDeliveredCount: number,
): string {
  if (action.key === "MARK_LOCAL_ORDER_READY") {
    return buildBulkLocalReadyConfirmationMessage(eligibleReadyCount);
  }
  if (action.key === "MARK_LOCAL_ORDER_COMPLETED") {
    return buildBulkLocalCompletedConfirmationMessage(eligibleCompletedCount);
  }
  if (action.key === "CREATE_SUPPLIER_PURCHASE") {
    return buildBulkCreateSupplierPurchaseConfirmationMessage(
      eligibleCreatePurchaseCount,
      selectedCount - eligibleCreatePurchaseCount,
    );
  }
  if (action.key === "RECEIVE_GOODS") {
    return buildBulkReceiveGoodsConfirmationMessage(
      eligibleReceiveGoodsCount,
      selectedCount - eligibleReceiveGoodsCount,
    );
  }
  if (action.key === "MARK_QC_PASSED") {
    return buildBulkMarkQcPassedConfirmationMessage(
      eligibleMarkQcPassedCount,
      selectedCount - eligibleMarkQcPassedCount,
    );
  }
  if (action.key === "MARK_CHINA_PACKING_COMPLETE") {
    return buildBulkMarkChinaPackingCompleteConfirmationMessage(
      eligibleMarkChinaPackingCompleteCount,
      selectedCount - eligibleMarkChinaPackingCompleteCount,
    );
  }
  if (action.key === "MARK_EXPORT_READY") {
    return buildBulkMarkExportReadyConfirmationMessage(
      eligibleMarkExportReadyCount,
      selectedCount - eligibleMarkExportReadyCount,
    );
  }
  if (action.key === "MARK_AGENT_DELIVERED") {
    return buildBulkMarkAgentDeliveredConfirmationMessage(
      eligibleMarkAgentDeliveredCount,
      selectedCount - eligibleMarkAgentDeliveredCount,
    );
  }
  if (action.key === "CREATE_SHIPMENT") {
    return buildBulkCreateShipmentConfirmationMessage(
      eligibleCreateShipmentCount,
      selectedCount - eligibleCreateShipmentCount,
    );
  }
  if (action.key === "MARK_CUSTOMER_COLLECTED") {
    return buildBulkMarkCustomerCollectedConfirmationMessage(
      eligibleMarkCustomerCollectedCount,
      selectedCount - eligibleMarkCustomerCollectedCount,
    );
  }
  if (action.key === "MARK_CUSTOMER_DELIVERED") {
    return buildBulkMarkCustomerDeliveredConfirmationMessage(
      eligibleMarkCustomerDeliveredCount,
      selectedCount - eligibleMarkCustomerDeliveredCount,
    );
  }
  return action.confirmation_message;
}

export function AdminFulfillmentBulkActionBar({
  selectedCount,
  selectedRows,
  permissions,
  onClearSelection,
  onCompleted,
}: AdminFulfillmentBulkActionBarProps) {
  const eligibleReadyCount = useMemo(
    () => countEligibleForBulkLocalReady(selectedRows),
    [selectedRows],
  );
  const eligibleCompletedCount = useMemo(
    () => countEligibleForBulkLocalCompleted(selectedRows),
    [selectedRows],
  );
  const eligibleCreatePurchaseCount = useMemo(
    () => countEligibleForBulkCreateSupplierPurchase(selectedRows),
    [selectedRows],
  );
  const eligibleReceiveGoodsCount = useMemo(
    () => countEligibleForBulkReceiveGoods(selectedRows),
    [selectedRows],
  );
  const eligibleMarkQcPassedCount = useMemo(
    () => countEligibleForBulkMarkQcPassed(selectedRows),
    [selectedRows],
  );
  const eligibleMarkChinaPackingCompleteCount = useMemo(
    () => countEligibleForBulkMarkChinaPackingComplete(selectedRows),
    [selectedRows],
  );
  const eligibleMarkExportReadyCount = useMemo(
    () => countEligibleForBulkMarkExportReady(selectedRows),
    [selectedRows],
  );
  const eligibleMarkAgentDeliveredCount = useMemo(
    () => countEligibleForBulkMarkAgentDelivered(selectedRows),
    [selectedRows],
  );
  const eligibleCreateShipmentCount = useMemo(
    () => countEligibleForBulkCreateShipment(selectedRows),
    [selectedRows],
  );
  const eligibleMarkCustomerCollectedCount = useMemo(
    () => countEligibleForBulkMarkCustomerCollected(selectedRows),
    [selectedRows],
  );
  const eligibleMarkCustomerDeliveredCount = useMemo(
    () => countEligibleForBulkMarkCustomerDelivered(selectedRows),
    [selectedRows],
  );
  const availableActions = useMemo(
    () => resolveVisibleBulkActionsForSelection(permissions, selectedRows),
    [permissions, selectedRows],
  );
  const eligibleHints = useMemo(
    () => buildBulkEligibleHints(selectedCount, selectedRows, availableActions),
    [availableActions, selectedCount, selectedRows],
  );
  const [selectedActionKey, setSelectedActionKey] = useState<FulfillmentBulkActionKey | "">("");
  const [confirmAction, setConfirmAction] = useState<FulfillmentBulkActionDefinition | null>(
    null,
  );
  const [executing, setExecuting] = useState(false);
  const [executionError, setExecutionError] = useState<string | null>(null);
  const [result, setResult] = useState<BulkFulfillmentActionResponse | null>(null);
  const [showFailures, setShowFailures] = useState(false);

  if (selectedCount === 0 || availableActions.length === 0) {
    return null;
  }

  const selectedAction =
    availableActions.find((action) => action.key === selectedActionKey) ?? null;

  const skippedReasonGroups = result ? groupBulkResultReasons(result.results, "skipped") : [];
  const failedReasonGroups = result ? groupBulkResultReasons(result.results, "failed") : [];
  const issueResults = (result?.results ?? []).filter(
    (row) => row.status === "failed" || row.status === "skipped",
  );
  const successLabel = result ? resolveBulkSuccessLabel(result.action_key) : "Completed";
  const showLargeSelectionWarning = shouldWarnLargeBulkSelection(selectedCount);

  const handleApply = () => {
    if (!selectedAction || executing) {
      return;
    }
    setExecutionError(null);
    setConfirmAction(selectedAction);
  };

  const handleConfirm = async () => {
    if (!confirmAction || executing) {
      return;
    }

    setConfirmAction(null);
    setExecuting(true);
    setExecutionError(null);
    setResult(null);

    try {
      const response = await executeBulkFulfillmentAction({
        actionKey: confirmAction.key,
        fulfillmentIds: selectedRows.map((row) => row.id),
      });
      setResult(response);
      onCompleted?.(response);
      if (shouldClearBulkSelectionAfterSuccess(response)) {
        onClearSelection();
      }
    } catch (error) {
      setExecutionError(
        error instanceof AdminFulfillmentBulkApiError
          ? error.message
          : "Unable to complete bulk fulfilment action.",
      );
    } finally {
      setExecuting(false);
    }
  };

  return (
    <>
      <div className="flex flex-col gap-3 border-b border-[#c9a227]/30 bg-[#c9a227]/10 px-4 py-3 lg:flex-row lg:items-center">
        <div>
          <p className="text-sm font-semibold text-zinc-900">
            {selectedCount} fulfilment{selectedCount === 1 ? "" : "s"} selected
          </p>
          {eligibleHints.length > 0 ? (
            <p className="mt-1 text-xs text-zinc-600">{eligibleHints.join(" · ")}</p>
          ) : null}
        </div>

        <div className="flex flex-wrap items-center gap-2 lg:ml-auto">
          <label className="sr-only" htmlFor="admin-fulfillment-bulk-actions">
            Bulk actions for selected fulfilments
          </label>
          <select
            id="admin-fulfillment-bulk-actions"
            value={selectedActionKey}
            disabled={executing}
            onChange={(event) =>
              setSelectedActionKey(event.target.value as FulfillmentBulkActionKey | "")
            }
            className="min-h-10 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20 disabled:opacity-60"
          >
            <option value="">Bulk actions…</option>
            {availableActions.map((action) => (
              <option key={action.key} value={action.key}>
                {action.label}
              </option>
            ))}
          </select>
          <button
            type="button"
            disabled={!selectedAction || executing}
            onClick={handleApply}
            className="min-h-10 rounded-lg bg-[#c9a227] px-3 py-2 text-xs font-semibold text-zinc-900 transition hover:bg-[#e8c547] disabled:cursor-not-allowed disabled:opacity-60"
          >
            Review action
          </button>
          <button
            type="button"
            disabled={executing}
            onClick={() => {
              setExecutionError(null);
              setResult(null);
              onClearSelection();
            }}
            className="min-h-10 rounded-lg px-3 py-2 text-xs font-semibold text-zinc-500 transition hover:text-zinc-800 disabled:opacity-60"
          >
            Clear selection
          </button>
        </div>
      </div>

      {executing ? (
        <p className="border-b border-zinc-100 bg-zinc-50 px-4 py-2 text-xs font-medium text-zinc-700">
          Processing {selectedCount} order{selectedCount === 1 ? "" : "s"}…
        </p>
      ) : null}

      {executionError ? (
        <p className="border-b border-red-100 bg-red-50 px-4 py-2 text-xs font-medium text-red-700">
          {executionError}
        </p>
      ) : null}

      {result ? (
        <div className="border-b border-zinc-100 bg-zinc-50 px-4 py-3 text-xs text-zinc-700">
          <p className="font-semibold text-zinc-900">Bulk action complete</p>
          <p className="mt-1">
            {successLabel}: {result.succeeded} · Failed: {result.failed}
            {result.skipped > 0 ? ` · Skipped: ${result.skipped}` : ""}
          </p>
          {skippedReasonGroups.length > 0 ? (
            <div className="mt-2">
              <p className="font-semibold text-zinc-800">Skipped</p>
              <ul className="mt-1 space-y-1">
                {skippedReasonGroups.map((group) => (
                  <li key={group.reason_code}>
                    {group.label} ({group.count})
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
          {failedReasonGroups.length > 0 ? (
            <div className="mt-2">
              <p className="font-semibold text-zinc-800">Failed</p>
              <ul className="mt-1 space-y-1">
                {failedReasonGroups.map((group) => (
                  <li key={group.reason_code}>
                    {group.label} ({group.count})
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
          {issueResults.length > 0 ? (
            <button
              type="button"
              onClick={() => setShowFailures((current) => !current)}
              className="mt-2 font-semibold text-[#8b6914] underline-offset-2 hover:underline"
            >
              {showFailures ? "Hide item details" : "View item details"}
            </button>
          ) : null}
          {showFailures ? (
            <ul className="mt-2 space-y-1">
              {issueResults.map((row) => (
                <li key={row.fulfillment_id}>
                  <span className="font-mono">{row.fulfillment_id}</span>
                  {row.reason || row.error
                    ? `: ${row.reason ?? row.error}${row.reason_code ? ` [${row.reason_code}]` : ""}`
                    : ""}
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : null}

      {confirmAction ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/40 p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="bulk-action-confirm-title"
        >
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 id="bulk-action-confirm-title" className="text-lg font-bold text-zinc-900">
              {confirmAction.confirmation_title}
            </h2>
            {showLargeSelectionWarning ? (
              <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">
                {buildLargeBulkSelectionWarning(selectedCount)}
              </p>
            ) : null}
            <p className="mt-2 text-sm leading-relaxed text-zinc-600">
              {resolveConfirmationMessage(
                confirmAction,
                selectedCount,
                selectedRows,
                eligibleReadyCount,
                eligibleCompletedCount,
                eligibleCreatePurchaseCount,
                eligibleReceiveGoodsCount,
                eligibleMarkQcPassedCount,
                eligibleMarkChinaPackingCompleteCount,
                eligibleMarkExportReadyCount,
                eligibleMarkAgentDeliveredCount,
                eligibleCreateShipmentCount,
                eligibleMarkCustomerCollectedCount,
                eligibleMarkCustomerDeliveredCount,
              )}
            </p>
            <div className="mt-6 flex flex-wrap justify-end gap-2">
              <button
                type="button"
                onClick={() => setConfirmAction(null)}
                className="min-h-10 rounded-lg border border-zinc-200 px-4 py-2 text-sm font-semibold text-zinc-700 hover:border-zinc-300"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => void handleConfirm()}
                className="min-h-10 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800"
              >
                Confirm
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
