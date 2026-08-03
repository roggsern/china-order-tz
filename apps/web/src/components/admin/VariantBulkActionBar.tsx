"use client";

import { useState } from "react";
import {
  parseBulkNumericField,
  stockFieldLabel,
  summarizeVariantBulkResults,
  validateVariantBulkFields,
  type VariantBulkActionResponse,
  type VariantBulkFieldValues,
} from "@/lib/admin/variant-bulk";
import {
  AdminVariantBulkApiError,
  executeBulkVariantAction,
} from "@/lib/api/admin-variant-bulk";

type VariantBulkActionBarProps = {
  productId: string;
  selectedCount: number;
  selectedIds: string[];
  isChinaImport: boolean;
  onClearSelection: () => void;
  onCompleted?: (results: VariantBulkActionResponse[]) => void;
};

const emptyFields = (): VariantBulkFieldValues => ({
  sellingPrice: "",
  costPrice: "",
  stockQuantity: "",
});

export function VariantBulkActionBar({
  productId,
  selectedCount,
  selectedIds,
  isChinaImport,
  onClearSelection,
  onCompleted,
}: VariantBulkActionBarProps) {
  const [fields, setFields] = useState<VariantBulkFieldValues>(emptyFields);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [results, setResults] = useState<VariantBulkActionResponse[] | null>(null);

  if (selectedCount === 0) {
    return null;
  }

  const summary = results ? summarizeVariantBulkResults(results) : null;

  const applyUpdates = async () => {
    const validationError = validateVariantBulkFields(fields, isChinaImport);
    if (validationError) {
      setError(validationError);
      return;
    }

    setBusy(true);
    setError(null);
    setResults(null);

    const calls: Promise<VariantBulkActionResponse>[] = [];
    const selling = parseBulkNumericField(fields.sellingPrice);
    const cost = parseBulkNumericField(fields.costPrice);
    const stock = parseBulkNumericField(fields.stockQuantity);

    if (selling !== null) {
      calls.push(
        executeBulkVariantAction({
          productId,
          actionKey: "set_selling_price",
          variantIds: selectedIds,
          payload: { amount: selling },
        }),
      );
    }

    if (cost !== null) {
      calls.push(
        executeBulkVariantAction({
          productId,
          actionKey: "set_cost_price",
          variantIds: selectedIds,
          payload: { cost_price: cost },
        }),
      );
    }

    if (stock !== null && isChinaImport) {
      calls.push(
        executeBulkVariantAction({
          productId,
          actionKey: "set_commercial_stock",
          variantIds: selectedIds,
          payload: { available_quantity: Math.trunc(stock) },
        }),
      );
    }

    if (stock !== null && !isChinaImport) {
      calls.push(
        executeBulkVariantAction({
          productId,
          actionKey: "set_inventory_stock",
          variantIds: selectedIds,
          payload: { on_hand: Math.trunc(stock) },
        }),
      );
    }

    try {
      const responses = await Promise.all(calls);
      setResults(responses);
      onCompleted?.(responses);

      const { failed } = summarizeVariantBulkResults(responses);
      if (failed === 0) {
        setFields(emptyFields());
        onClearSelection();
      }
    } catch (err) {
      setError(
        err instanceof AdminVariantBulkApiError
          ? err.message
          : "Unable to execute bulk variant action.",
      );
    } finally {
      setBusy(false);
    }
  };

  const runStatusAction = async (actionKey: "activate" | "deactivate") => {
    setBusy(true);
    setError(null);
    setResults(null);

    try {
      const response = await executeBulkVariantAction({
        productId,
        actionKey,
        variantIds: selectedIds,
      });
      setResults([response]);
      onCompleted?.([response]);

      if (response.failed === 0) {
        onClearSelection();
      }
    } catch (err) {
      setError(
        err instanceof AdminVariantBulkApiError
          ? err.message
          : "Unable to execute bulk variant action.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="rounded-lg border border-zinc-200 bg-zinc-50">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3">
        <div>
          <p className="text-sm font-semibold text-zinc-900">
            {selectedCount} variant{selectedCount === 1 ? "" : "s"} selected
          </p>
          <p className="text-xs text-zinc-500">
            Apply pricing, {isChinaImport ? "commercial stock" : "warehouse stock"}, or status to
            selected variants.
          </p>
        </div>
        <button
          type="button"
          className="rounded px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100"
          disabled={busy}
          onClick={onClearSelection}
        >
          Clear selection
        </button>
      </div>

      <div className="grid gap-3 px-4 py-3 sm:grid-cols-2 lg:grid-cols-4">
        <label className="block text-xs text-zinc-600">
          <span className="mb-1 block font-medium text-zinc-700">Selling price (TZS)</span>
          <input
            type="number"
            min={0}
            step="0.01"
            value={fields.sellingPrice}
            disabled={busy}
            onChange={(event) =>
              setFields((current) => ({ ...current, sellingPrice: event.target.value }))
            }
            className="w-full rounded border border-zinc-300 px-2 py-1.5 text-sm"
            placeholder="Leave blank to skip"
          />
        </label>

        <label className="block text-xs text-zinc-600">
          <span className="mb-1 block font-medium text-zinc-700">Cost / buying price (TZS)</span>
          <input
            type="number"
            min={0}
            step="0.01"
            value={fields.costPrice}
            disabled={busy}
            onChange={(event) =>
              setFields((current) => ({ ...current, costPrice: event.target.value }))
            }
            className="w-full rounded border border-zinc-300 px-2 py-1.5 text-sm"
            placeholder="Leave blank to skip"
          />
        </label>

        <label className="block text-xs text-zinc-600">
          <span className="mb-1 block font-medium text-zinc-700">
            {stockFieldLabel(isChinaImport)}
          </span>
          <input
            type="number"
            min={0}
            step={1}
            value={fields.stockQuantity}
            disabled={busy}
            onChange={(event) =>
              setFields((current) => ({ ...current, stockQuantity: event.target.value }))
            }
            className="w-full rounded border border-zinc-300 px-2 py-1.5 text-sm"
            placeholder="Leave blank to skip"
          />
        </label>
      </div>

      <div className="flex flex-wrap items-center gap-2 border-t border-zinc-200 px-4 py-3">
        <button
          type="button"
          className="rounded bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
          disabled={busy}
          onClick={() => void applyUpdates()}
        >
          {busy ? "Applying…" : "Apply updates"}
        </button>
        <button
          type="button"
          className="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-white disabled:opacity-50"
          disabled={busy}
          onClick={() => void runStatusAction("activate")}
        >
          Activate
        </button>
        <button
          type="button"
          className="rounded border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-white disabled:opacity-50"
          disabled={busy}
          onClick={() => void runStatusAction("deactivate")}
        >
          Deactivate
        </button>
      </div>

      {error ? (
        <div className="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-800">
          {error}
        </div>
      ) : null}

      {summary && summary.failed > 0 ? (
        <div className="border-t border-amber-100 bg-amber-50 px-4 py-2 text-xs text-amber-900">
          {summary.succeeded} succeeded, {summary.failed} failed.
          <ul className="mt-1 list-disc pl-4">
            {summary.failures.slice(0, 5).map((row) => (
              <li key={row.variant_id}>
                {row.variant_id}: {row.message}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {summary && summary.failed === 0 && summary.succeeded > 0 ? (
        <div className="border-t border-emerald-100 bg-emerald-50 px-4 py-2 text-xs text-emerald-800">
          Bulk update completed for {summary.succeeded} variant
          {summary.succeeded === 1 ? "" : "s"}.
        </div>
      ) : null}
    </div>
  );
}
