"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminWarehouseOpsApiError,
  approveWarehouseTransfer,
  canTransferWarehouse,
  canViewWarehouse,
  completeWarehouseTransfer,
  fetchWarehouseTransfers,
  type WarehouseTransfer,
} from "@/lib/api/admin-warehouse-operations";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

export function AdminWarehouseTransfersPanel() {
  const { permissions } = useAdminPermissions();
  const canView = canViewWarehouse(permissions) || canTransferWarehouse(permissions);
  const canTransfer = canTransferWarehouse(permissions);

  const [rows, setRows] = useState<WarehouseTransfer[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      setRows(await fetchWarehouseTransfers());
    } catch (err) {
      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Unable to load transfers.");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const act = async (id: string, action: "approve" | "complete") => {
    setError(null);
    try {
      if (action === "approve") await approveWarehouseTransfer(id);
      else await completeWarehouseTransfer(id);
      await reload();
    } catch (err) {
      setError(err instanceof AdminWarehouseOpsApiError ? err.message : "Transfer action failed.");
    }
  };

  if (!canView) {
    return <div className="p-6 text-sm text-zinc-600">You do not have permission to view transfers.</div>;
  }

  return (
    <div className="p-4 lg:p-8">
      {error ? <p className="mb-3 text-sm text-red-700">{error}</p> : null}
      <div className="admin-card overflow-hidden">
        <div className="border-b border-zinc-200 px-4 py-3 text-sm font-semibold">Stock transfers</div>
        {loading ? (
          <p className="p-4 text-sm text-zinc-500">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="p-4 text-sm text-zinc-500">No warehouse transfers.</p>
        ) : (
          <table className="min-w-full text-sm">
            <thead className="bg-zinc-50 text-left text-xs uppercase text-zinc-500">
              <tr>
                <th className="px-4 py-2">Transfer</th>
                <th className="px-4 py-2">From → To</th>
                <th className="px-4 py-2">Status</th>
                <th className="px-4 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-t border-zinc-100">
                  <td className="px-4 py-3 font-mono text-xs">{row.transfer_number}</td>
                  <td className="px-4 py-3">
                    {row.from_facility?.code} → {row.to_facility?.code}
                  </td>
                  <td className="px-4 py-3">{row.status_label ?? row.status}</td>
                  <td className="px-4 py-3">
                    {canTransfer && row.status === "requested" ? (
                      <button type="button" className="admin-btn-secondary text-xs" onClick={() => void act(row.id, "approve")}>
                        Approve
                      </button>
                    ) : null}
                    {canTransfer && row.status === "approved" ? (
                      <button type="button" className="admin-btn-primary text-xs" onClick={() => void act(row.id, "complete")}>
                        Complete transfer
                      </button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
