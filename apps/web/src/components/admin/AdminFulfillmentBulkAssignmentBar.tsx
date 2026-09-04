"use client";

import { useEffect, useRef, useState } from "react";
import {
  buildBulkAssignmentPayload,
  buildBulkAssignmentSuccessMessage,
} from "@/lib/admin/fulfillment-bulk-assignment";
import {
  AdminFulfillmentBulkAssignmentApiError,
  updateAdminFulfillmentBulkAssignment,
} from "@/lib/api/admin-fulfillment-bulk-assignment";
import {
  AdminFulfillmentApiError,
  fetchFulfillmentAssignees,
  type FulfillmentAssigneeOption,
} from "@/lib/api/admin-fulfillments";

type AdminFulfillmentBulkAssignmentBarProps = {
  selectedIds: Set<string>;
  visibleIds: readonly string[];
  canManage: boolean;
  hadExistingAssignee?: boolean;
  onSuccess?: (message: string) => void;
};

export function AdminFulfillmentBulkAssignmentBar({
  selectedIds,
  visibleIds,
  canManage,
  hadExistingAssignee = false,
  onSuccess,
}: AdminFulfillmentBulkAssignmentBarProps) {
  const fulfillmentIds = buildBulkAssignmentPayload(selectedIds, visibleIds, null)
    .fulfillment_ids;
  const selectedCount = fulfillmentIds.length;
  const [options, setOptions] = useState<FulfillmentAssigneeOption[]>([]);
  const [selectedOperatorId, setSelectedOperatorId] = useState("");
  const [loadingOptions, setLoadingOptions] = useState(false);
  const [optionsError, setOptionsError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const savingRef = useRef(false);

  const shouldLoad = canManage && selectedCount > 0;

  useEffect(() => {
    if (!shouldLoad) {
      return;
    }

    let cancelled = false;
    setLoadingOptions(true);
    setOptionsError(null);

    void fetchFulfillmentAssignees()
      .then((rows) => {
        if (!cancelled) {
          setOptions(rows);
        }
      })
      .catch((error) => {
        if (!cancelled) {
          setOptions([]);
          setOptionsError(
            error instanceof AdminFulfillmentApiError
              ? error.message
              : "Unable to load operators.",
          );
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingOptions(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [shouldLoad]);

  if (!canManage || selectedCount === 0) {
    return null;
  }

  async function persist(assignedTo: string | null) {
    if (savingRef.current) {
      return;
    }

    const payload = buildBulkAssignmentPayload(selectedIds, visibleIds, assignedTo);
    if (payload.fulfillment_ids.length === 0) {
      return;
    }

    savingRef.current = true;
    setSaving(true);
    setActionError(null);

    try {
      const result = await updateAdminFulfillmentBulkAssignment(
        payload.fulfillment_ids,
        payload.assigned_to,
      );
      const assigneeName =
        result.assignee?.name ??
        options.find((option) => option.id === assignedTo)?.name ??
        null;
      onSuccess?.(
        buildBulkAssignmentSuccessMessage({
          requested: result.requested,
          assignedTo: result.assigned_to,
          assigneeName,
          hadExistingAssignee,
        }),
      );
      setSelectedOperatorId("");
    } catch (error) {
      setActionError(
        error instanceof AdminFulfillmentBulkAssignmentApiError
          ? error.message
          : "Unable to update fulfillment assignments.",
      );
    } finally {
      savingRef.current = false;
      setSaving(false);
    }
  }

  return (
    <div className="hidden border-b border-zinc-100 bg-white px-4 py-3 lg:block">
      <div className="flex flex-wrap items-center gap-3">
        <p className="text-sm font-semibold text-zinc-800">
          {selectedCount} selected on this page
        </p>
        {loadingOptions ? (
          <p className="text-xs text-zinc-500">Loading operators…</p>
        ) : null}
        <select
          value={selectedOperatorId}
          onChange={(event) => setSelectedOperatorId(event.target.value)}
          disabled={saving || loadingOptions || options.length === 0}
          aria-label="Select operator"
          className="admin-input min-w-[200px]"
        >
          <option value="">Select operator</option>
          {options.map((option) => (
            <option key={option.id} value={option.id}>
              {option.email ? `${option.name} (${option.email})` : option.name}
            </option>
          ))}
        </select>
        <button
          type="button"
          disabled={saving || !selectedOperatorId}
          onClick={() => void persist(selectedOperatorId || null)}
          className="admin-btn-primary disabled:opacity-50"
        >
          {saving ? "Saving…" : "Assign"}
        </button>
        <button
          type="button"
          disabled={saving}
          onClick={() => void persist(null)}
          className="inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:border-zinc-300 disabled:opacity-50"
        >
          Unassign
        </button>
      </div>
      {optionsError ? (
        <p className="mt-2 text-xs text-red-700" role="alert">
          {optionsError}
        </p>
      ) : null}
      {actionError ? (
        <p className="mt-2 text-xs text-red-700" role="alert">
          {actionError}
        </p>
      ) : null}
    </div>
  );
}
