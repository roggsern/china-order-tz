"use client";

import { useMemo, useState } from "react";
import {
  buildProductBulkConfirmationMessage,
  groupProductBulkFailures,
  resolveVisibleProductBulkActions,
  summarizeProductBulkResults,
  validateProductBulkPayload,
  type ProductBulkActionDefinition,
  type ProductBulkActionPayload,
  type ProductBulkActionResponse,
} from "@/lib/admin/product-bulk";
import {
  AdminProductBulkApiError,
  executeBulkProductAction,
} from "@/lib/api/admin-product-bulk";

type AdminProductBulkActionBarProps = {
  selectedCount: number;
  selectedIds: string[];
  permissions?: string[];
  onClearSelection: () => void;
  onCompleted?: (result: ProductBulkActionResponse) => void;
};

export function AdminProductBulkActionBar({
  selectedCount,
  selectedIds,
  permissions,
  onClearSelection,
  onCompleted,
}: AdminProductBulkActionBarProps) {
  const actions = useMemo(
    () => resolveVisibleProductBulkActions(permissions),
    [permissions],
  );
  const [activeAction, setActiveAction] = useState<ProductBulkActionDefinition | null>(null);
  const [payload, setPayload] = useState<ProductBulkActionPayload>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<ProductBulkActionResponse | null>(null);

  if (selectedCount === 0 || actions.length === 0) {
    return null;
  }

  const openAction = (action: ProductBulkActionDefinition) => {
    setActiveAction(action);
    setPayload({});
    setError(null);
    setResult(null);
  };

  const closeAction = () => {
    if (busy) return;
    setActiveAction(null);
    setPayload({});
    setError(null);
  };

  const runAction = async () => {
    if (!activeAction) return;
    const validationError = validateProductBulkPayload(activeAction, payload);
    if (validationError) {
      setError(validationError);
      return;
    }

    setBusy(true);
    setError(null);
    try {
      const next = await executeBulkProductAction({
        actionKey: activeAction.key,
        productIds: selectedIds,
        payload,
      });
      setResult(next);
      onCompleted?.(next);
      if (next.failed === 0) {
        onClearSelection();
      }
    } catch (err) {
      setError(
        err instanceof AdminProductBulkApiError
          ? err.message
          : "Unable to execute bulk product action.",
      );
    } finally {
      setBusy(false);
    }
  };

  const failures = result ? groupProductBulkFailures(result.results) : [];

  return (
    <div className="admin-card mb-4 overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3">
        <div>
          <p className="text-sm font-semibold text-zinc-900">
            {selectedCount} product{selectedCount === 1 ? "" : "s"} selected
          </p>
          <p className="text-xs text-zinc-500">
            Bulk publish, archive, pricing, and inventory use existing engines.
          </p>
        </div>
        <button
          type="button"
          className="rounded px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100"
          onClick={onClearSelection}
        >
          Clear selection
        </button>
      </div>

      <div className="flex flex-wrap gap-2 px-4 py-3">
        {actions.map((action) => (
          <button
            key={action.key}
            type="button"
            className="admin-btn-secondary"
            onClick={() => openAction(action)}
          >
            {action.label}
          </button>
        ))}
      </div>

      {activeAction ? (
        <div className="border-t border-zinc-200 bg-zinc-50/70 px-4 py-4">
          <p className="text-sm font-medium text-zinc-900">
            {buildProductBulkConfirmationMessage(activeAction, selectedCount, payload)}
          </p>

          <div className="mt-3 flex flex-wrap items-end gap-3">
            {activeAction.needsPercent ? (
              <label className="block text-xs font-semibold text-zinc-500">
                Percent
                <input
                  type="number"
                  min={0.01}
                  step={0.01}
                  className="admin-input mt-1 w-28"
                  value={payload.percent ?? ""}
                  onChange={(event) =>
                    setPayload((current) => ({
                      ...current,
                      percent: event.target.value === "" ? undefined : Number(event.target.value),
                    }))
                  }
                />
              </label>
            ) : null}
            {activeAction.needsAmount ? (
              <label className="block text-xs font-semibold text-zinc-500">
                Amount (TZS)
                <input
                  type="number"
                  min={0}
                  step={0.01}
                  className="admin-input mt-1 w-36"
                  value={payload.amount ?? ""}
                  onChange={(event) =>
                    setPayload((current) => ({
                      ...current,
                      amount: event.target.value === "" ? undefined : Number(event.target.value),
                    }))
                  }
                />
              </label>
            ) : null}
            {activeAction.needsQuantity ? (
              <label className="block text-xs font-semibold text-zinc-500">
                Quantity
                <input
                  type="number"
                  min={0}
                  step={1}
                  className="admin-input mt-1 w-28"
                  value={payload.quantity ?? ""}
                  onChange={(event) =>
                    setPayload((current) => ({
                      ...current,
                      quantity:
                        event.target.value === "" ? undefined : Number(event.target.value),
                    }))
                  }
                />
              </label>
            ) : null}
            <button
              type="button"
              className="admin-btn-primary"
              disabled={busy}
              onClick={() => void runAction()}
            >
              {busy ? "Running…" : "Confirm"}
            </button>
            <button
              type="button"
              className="admin-btn-secondary"
              disabled={busy}
              onClick={closeAction}
            >
              Cancel
            </button>
          </div>

          {error ? (
            <div className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {error}
            </div>
          ) : null}

          {result ? (
            <div className="mt-3 rounded-lg border border-zinc-200 bg-white px-3 py-3 text-sm text-zinc-700">
              <p className="font-medium text-zinc-900">{summarizeProductBulkResults(result)}</p>
              {failures.length > 0 ? (
                <ul className="mt-2 space-y-1 text-xs text-zinc-600">
                  {failures.map((failure) => (
                    <li key={failure.message}>
                      {failure.count}× {failure.message}
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
