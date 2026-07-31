"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminWarehouseOpsApiError,
  canManageWarehouse,
  canViewWarehouse,
  completeWarehousePickList,
  fetchWarehousePickList,
  fetchWarehousePickLists,
  startWarehousePickList,
  updateWarehousePickLine,
  type WarehousePickList,
} from "@/lib/api/admin-warehouse-operations";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

export function AdminWarehousePickListsPanel({
  initialSelectedId = null,
  onMapsChange,
}: {
  initialSelectedId?: string | null;
  onMapsChange?: () => Promise<void>;
} = {}) {
  const { permissions } = useAdminPermissions();
  const canView = canViewWarehouse(permissions);
  const canManage = canManageWarehouse(permissions);

  const [rows, setRows] = useState<WarehousePickList[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<WarehousePickList | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(async () => {
    if (!canView) {
      setRows([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      setRows(await fetchWarehousePickLists());
    } catch (err) {
      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Unable to load pick lists.");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    if (initialSelectedId) {
      setSelectedId(initialSelectedId);
    }
  }, [initialSelectedId]);

  useEffect(() => {
    if (!selectedId) {
      setDetail(null);
      return;
    }
    void fetchWarehousePickList(selectedId)
      .then(setDetail)
      .catch((err) => setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Unable to load pick list."));
  }, [selectedId]);

  const run = async (action: "start" | "complete") => {
    if (!detail || !canManage) return;
    setBusy(true);
    setError(null);
    try {
      const updated = action === "start"
        ? await startWarehousePickList(detail.id)
        : await completeWarehousePickList(detail.id);
      setDetail(updated);
      await reload();
      await onMapsChange?.();
    } catch (err) {
      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Pick list action failed.");
    } finally {
      setBusy(false);
    }
  };

  const updateLineQty = async (lineId: string, qty: number) => {
    if (!detail || !canManage) return;
    setBusy(true);
    try {
      await updateWarehousePickLine(detail.id, lineId, qty);
      setDetail(await fetchWarehousePickList(detail.id));
    } catch (err) {
      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Unable to update line.");
    } finally {
      setBusy(false);
    }
  };

  if (!canView) {
    return <div className="p-6 text-sm text-zinc-600">You do not have permission to view pick lists.</div>;
  }

  return (
    <div className="grid gap-4 p-4 lg:grid-cols-[1.2fr_1fr] lg:p-8">
      <div className="admin-card overflow-hidden">
        <div className="border-b border-zinc-200 px-4 py-3 text-sm font-semibold">Pick lists</div>
        {loading ? (
          <p className="p-4 text-sm text-zinc-500">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="p-4 text-sm text-zinc-500">No pick lists yet. Create one from a warehouse job.</p>
        ) : (
          <ul className="divide-y divide-zinc-100">
            {rows.map((row) => (
              <li key={row.id}>
                <button
                  type="button"
                  className={`w-full px-4 py-3 text-left hover:bg-zinc-50 ${selectedId === row.id ? "bg-zinc-50" : ""}`}
                  onClick={() => setSelectedId(row.id)}
                >
                  <div className="flex justify-between gap-2">
                    <span className="font-medium">{row.order?.order_number ?? row.order_id}</span>
                    <span className="text-xs uppercase text-zinc-500">{row.status_label ?? row.status}</span>
                  </div>
                  <span className="text-xs text-zinc-500">{row.warehouse_job?.job_number}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="admin-card p-4">
        <h2 className="text-sm font-semibold">Pick details</h2>
        {error ? <p className="mt-2 text-sm text-red-700">{error}</p> : null}
        {!detail ? (
          <p className="mt-3 text-sm text-zinc-500">Select a pick list.</p>
        ) : (
          <div className="mt-3 space-y-3 text-sm">
            <p>Order {detail.order?.order_number} · {detail.status_label ?? detail.status}</p>
            <ul className="space-y-2">
              {detail.lines?.map((line) => (
                <li key={line.id} className="rounded-lg border border-zinc-200 p-2">
                  <p className="font-medium">{line.product_name}</p>
                  <p className="text-xs text-zinc-500">
                    {line.sku ? `${line.sku} · ` : ""}
                    {line.warehouse_bin?.code ? `Bin ${line.warehouse_bin.code}` : "No bin assigned"}
                  </p>
                  {canManage ? (
                    <div className="mt-2 flex items-center gap-2">
                      <input
                        type="number"
                        min={0}
                        max={line.quantity}
                        className="admin-input w-20 text-xs"
                        defaultValue={line.picked_quantity}
                        onBlur={(e) => void updateLineQty(line.id, Number.parseInt(e.target.value, 10) || 0)}
                        disabled={busy}
                      />
                      <span className="text-xs text-zinc-500">/ {line.quantity}</span>
                    </div>
                  ) : (
                    <p className="text-xs">{line.picked_quantity}/{line.quantity} picked</p>
                  )}
                </li>
              ))}
            </ul>
            {canManage ? (
              <div className="flex gap-2">
                <button type="button" className="admin-btn-secondary text-xs" disabled={busy} onClick={() => void run("start")}>
                  Start pick
                </button>
                <button type="button" className="admin-btn-primary text-xs" disabled={busy} onClick={() => void run("complete")}>
                  Complete pick
                </button>
              </div>
            ) : null}
          </div>
        )}
      </div>
    </div>
  );
}
