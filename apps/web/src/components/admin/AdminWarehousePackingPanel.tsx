"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminWarehouseOpsApiError,
  canManageWarehouse,
  canViewWarehouse,
  completeWarehousePacking,
  fetchWarehousePacking,
  startWarehousePacking,
  updateWarehousePackingLine,
  type WarehousePackingRecord,
} from "@/lib/api/admin-warehouse-operations";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

export function AdminWarehousePackingPanel({
  initialSelectedId = null,
  onMapsChange,
}: {
  initialSelectedId?: string | null;
  onMapsChange?: () => Promise<void>;
} = {}) {
  const { permissions } = useAdminPermissions();
  const canView = canViewWarehouse(permissions);
  const canManage = canManageWarehouse(permissions);

  const [rows, setRows] = useState<WarehousePackingRecord[]>([]);
  const [selected, setSelected] = useState<WarehousePackingRecord | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      setRows(await fetchWarehousePacking());
    } catch (err) {
      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Unable to load packing queue.");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    if (!initialSelectedId || rows.length === 0) return;
    const match = rows.find((row) => row.id === initialSelectedId);
    if (match) setSelected(match);
  }, [initialSelectedId, rows]);

  if (!canView) {
    return <div className="p-6 text-sm text-zinc-600">You do not have permission to view packing.</div>;
  }

  return (
    <div className="grid gap-4 p-4 lg:grid-cols-[1.2fr_1fr] lg:p-8">
      <div className="admin-card overflow-hidden">
        <div className="border-b border-zinc-200 px-4 py-3 text-sm font-semibold">Packing queue</div>
        {loading ? (
          <p className="p-4 text-sm text-zinc-500">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="p-4 text-sm text-zinc-500">No packing records.</p>
        ) : (
          <ul className="divide-y divide-zinc-100">
            {rows.map((row) => (
              <li key={row.id}>
                <button
                  type="button"
                  className="w-full px-4 py-3 text-left hover:bg-zinc-50"
                  onClick={() => setSelected(row)}
                >
                  <div className="flex justify-between">
                    <span>{row.warehouse_job?.order?.order_number ?? row.warehouse_job_id}</span>
                    <span className="text-xs uppercase text-zinc-500">{row.status_label ?? row.status}</span>
                  </div>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="admin-card p-4">
        <h2 className="text-sm font-semibold">Packing</h2>
        {error ? <p className="mt-2 text-sm text-red-700">{error}</p> : null}
        {!selected ? (
          <p className="mt-3 text-sm text-zinc-500">Select a packing record.</p>
        ) : (
          <div className="mt-3 space-y-3 text-sm">
            <p>{selected.package_status ?? "pending"} · {selected.status_label ?? selected.status}</p>
            {selected.notes ? <p className="text-xs text-zinc-500">{selected.notes}</p> : null}
            <ul className="space-y-2">
              {selected.lines?.map((line) => (
                <li key={line.id} className="flex items-center gap-2">
                  {canManage ? (
                    <>
                      <input
                        type="number"
                        min={0}
                        max={line.quantity}
                        className="admin-input w-20 text-xs"
                        defaultValue={line.packed_quantity}
                        disabled={busy}
                        onBlur={async (e) => {
                          setBusy(true);
                          try {
                            await updateWarehousePackingLine(selected.id, line.id, Number.parseInt(e.target.value, 10) || 0);
                            setSelected((await fetchWarehousePacking()).find((r) => r.id === selected.id) ?? selected);
                          } finally {
                            setBusy(false);
                          }
                        }}
                      />
                      <span>/ {line.quantity}</span>
                    </>
                  ) : (
                    <span>{line.packed_quantity}/{line.quantity}</span>
                  )}
                </li>
              ))}
            </ul>
            {canManage ? (
              <div className="flex gap-2">
                <button
                  type="button"
                  className="admin-btn-secondary text-xs"
                  disabled={busy}
                  onClick={async () => {
                    setBusy(true);
                    try {
                      setSelected(await startWarehousePacking(selected.id));
                      await reload();
                      await onMapsChange?.();
                    } catch (err) {
                      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Failed.");
                    } finally {
                      setBusy(false);
                    }
                  }}
                >
                  Start packing
                </button>
                <button
                  type="button"
                  className="admin-btn-primary text-xs"
                  disabled={busy}
                  onClick={async () => {
                    setBusy(true);
                    try {
                      setSelected(await completeWarehousePacking(selected.id));
                      await reload();
                      await onMapsChange?.();
                    } catch (err) {
                      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Failed.");
                    } finally {
                      setBusy(false);
                    }
                  }}
                >
                  Complete packing
                </button>
              </div>
            ) : null}
          </div>
        )}
      </div>
    </div>
  );
}
