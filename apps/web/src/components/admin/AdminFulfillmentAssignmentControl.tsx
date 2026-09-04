"use client";

import { useEffect, useRef, useState } from "react";
import {
  AdminFulfillmentApiError,
  fetchFulfillmentAssignees,
  updateAdminFulfillmentAssignment,
  type FulfillmentAssigneeOption,
} from "@/lib/api/admin-fulfillments";

type Assignee = {
  id: string;
  name: string;
  email?: string;
} | null;

export function AdminFulfillmentAssignmentControl({
  fulfillmentId,
  assignedTo,
  assignee,
  canManage,
  onAssigned,
}: {
  fulfillmentId: string;
  assignedTo?: string | null;
  assignee?: Assignee;
  canManage: boolean;
  onAssigned?: (next: { assignedTo: string | null; assignee: Assignee }) => void;
}) {
  const [currentAssignee, setCurrentAssignee] = useState<Assignee>(assignee ?? null);
  const [selectedId, setSelectedId] = useState("");
  const [options, setOptions] = useState<FulfillmentAssigneeOption[]>([]);
  const [loadingOptions, setLoadingOptions] = useState(false);
  const [optionsError, setOptionsError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const savingRef = useRef(false);

  useEffect(() => {
    setCurrentAssignee(assignee ?? null);
  }, [assignedTo, assignee]);

  useEffect(() => {
    if (!canManage) {
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
  }, [canManage]);

  const currentId = currentAssignee?.id ?? assignedTo ?? null;
  const displayName = currentAssignee?.name ?? "Unassigned";
  const isAssigned = Boolean(currentId);

  async function persist(nextId: string | null) {
    if (savingRef.current) {
      return;
    }
    if ((nextId ?? null) === (currentId ?? null)) {
      setSelectedId("");
      setActionError(null);
      return;
    }

    savingRef.current = true;
    setSaving(true);
    setActionError(null);

    try {
      const updated = await updateAdminFulfillmentAssignment(fulfillmentId, nextId);
      const nextAssignee = updated.assignee
        ? { id: updated.assignee.id, name: updated.assignee.name, email: updated.assignee.email }
        : null;
      setCurrentAssignee(nextAssignee);
      setSelectedId("");
      onAssigned?.({ assignedTo: updated.assigned_to ?? null, assignee: nextAssignee });
    } catch (error) {
      setActionError(
        error instanceof AdminFulfillmentApiError
          ? error.message
          : "Unable to update assignment.",
      );
    } finally {
      savingRef.current = false;
      setSaving(false);
    }
  }

  return (
    <div className="rounded-xl border border-zinc-100 bg-zinc-50/60 px-3 py-3">
      <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
        Assigned operator
      </p>
      <p className="mt-1 text-sm font-semibold text-zinc-900">{displayName}</p>

      {canManage ? (
        <div className="mt-3 space-y-2">
          {loadingOptions ? (
            <p className="text-xs text-zinc-500">Loading operators…</p>
          ) : null}
          {optionsError ? (
            <p className="text-xs text-red-700" role="alert">
              {optionsError}
            </p>
          ) : null}

          <div className="flex flex-wrap gap-2">
            <select
              value={selectedId}
              onChange={(event) => setSelectedId(event.target.value)}
              disabled={saving || loadingOptions || options.length === 0}
              aria-label="Select operator"
              className="admin-input min-w-[200px] flex-1"
            >
              <option value="">{isAssigned ? "Reassign to…" : "Select operator"}</option>
              {options.map((option) => (
                <option key={option.id} value={option.id}>
                  {option.email ? `${option.name} (${option.email})` : option.name}
                </option>
              ))}
            </select>
            <button
              type="button"
              disabled={saving || !selectedId}
              onClick={() => void persist(selectedId || null)}
              className="admin-btn-primary disabled:opacity-50"
            >
              {saving ? "Saving…" : isAssigned ? "Reassign" : "Assign"}
            </button>
            {isAssigned ? (
              <button
                type="button"
                disabled={saving}
                onClick={() => void persist(null)}
                className="inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:border-zinc-300 disabled:opacity-50"
              >
                Unassign
              </button>
            ) : null}
          </div>

          {actionError ? (
            <p className="text-xs text-red-700" role="alert">
              {actionError}
            </p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
