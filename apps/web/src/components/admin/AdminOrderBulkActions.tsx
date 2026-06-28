"use client";

import { useState } from "react";
import {
  BULK_ORDER_STATUSES,
  bulkOrderStatusLabel,
  type BulkOrderStatus,
} from "@/lib/admin/bulk-order-status";

interface AdminOrderBulkActionsProps {
  selectedCount: number;
  isUpdating: boolean;
  onApply: (status: BulkOrderStatus) => Promise<void>;
  onClear: () => void;
}

export function AdminOrderBulkActions({
  selectedCount,
  isUpdating,
  onApply,
  onClear,
}: AdminOrderBulkActionsProps) {
  const [selectedAction, setSelectedAction] = useState<BulkOrderStatus | "">("");

  if (selectedCount === 0) {
    return null;
  }

  const handleApply = async () => {
    if (!selectedAction || isUpdating) {
      return;
    }

    await onApply(selectedAction);
    setSelectedAction("");
  };

  return (
    <div className="flex flex-col gap-3 border-b border-[#c9a227]/30 bg-[#c9a227]/10 px-4 py-3 sm:flex-row sm:items-center">
      <p className="text-sm font-semibold text-zinc-900">
        {selectedCount} order{selectedCount === 1 ? "" : "s"} selected
        {isUpdating && (
          <span className="ml-2 inline-flex items-center gap-1 text-xs font-medium text-zinc-600">
            <span className="inline-block h-3 w-3 animate-spin rounded-full border-2 border-zinc-400 border-t-transparent" />
            Updating…
          </span>
        )}
      </p>
      <div className="flex flex-wrap items-center gap-2 sm:ml-auto">
        <label className="sr-only" htmlFor="admin-order-bulk-actions">
          Bulk actions for selected orders
        </label>
        <select
          id="admin-order-bulk-actions"
          value={selectedAction}
          disabled={isUpdating}
          onChange={(event) => setSelectedAction(event.target.value as BulkOrderStatus | "")}
          className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/20 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <option value="">Bulk actions…</option>
          {BULK_ORDER_STATUSES.map((status) => (
            <option key={status} value={status}>
              Mark as {bulkOrderStatusLabel(status)}
            </option>
          ))}
        </select>
        <button
          type="button"
          disabled={!selectedAction || isUpdating}
          onClick={() => void handleApply()}
          className="rounded-lg bg-[#c9a227] px-3 py-1.5 text-xs font-semibold text-zinc-900 transition hover:bg-[#e8c547] disabled:cursor-not-allowed disabled:opacity-60"
        >
          Apply
        </button>
        <button
          type="button"
          disabled={isUpdating}
          onClick={onClear}
          className="rounded-lg px-3 py-1.5 text-xs font-semibold text-zinc-500 transition hover:text-zinc-800 disabled:opacity-60"
        >
          Clear
        </button>
      </div>
    </div>
  );
}
