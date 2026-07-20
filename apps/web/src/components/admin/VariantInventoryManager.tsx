"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminVariantInventory,
  deleteAdminVariantInventory,
  fetchAdminVariantInventories,
  updateAdminVariantInventory,
  type AdminVariantInventory,
} from "@/lib/api/admin-catalog";

type VariantInventoryManagerProps = {
  variantId: string;
  variantLabel: string;
  onClose?: () => void;
};

type InventoryForm = {
  warehouseCode: string;
  onHand: string;
  reserved: string;
  reorderLevel: string;
  safetyStock: string;
  isActive: boolean;
};

const emptyForm = (): InventoryForm => ({
  warehouseCode: "MAIN",
  onHand: "0",
  reserved: "0",
  reorderLevel: "5",
  safetyStock: "0",
  isActive: true,
});

export function VariantInventoryManager({
  variantId,
  variantLabel,
  onClose,
}: VariantInventoryManagerProps) {
  const [rows, setRows] = useState<AdminVariantInventory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<InventoryForm>(emptyForm());
  const [adjustQty, setAdjustQty] = useState("1");

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      setRows(await fetchAdminVariantInventories(variantId));
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load inventory.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [variantId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const startCreate = () => {
    setEditingId(null);
    setForm(emptyForm());
    setSuccess(null);
    setError(null);
  };

  const startEdit = (row: AdminVariantInventory) => {
    setEditingId(row.id);
    setForm({
      warehouseCode: row.warehouseCode,
      onHand: String(row.onHand),
      reserved: String(row.reserved),
      reorderLevel: String(row.reorderLevel),
      safetyStock: String(row.safetyStock),
      isActive: row.isActive,
    });
    setSuccess(null);
    setError(null);
  };

  const handleSave = async () => {
    const onHand = Number(form.onHand);
    const reserved = Number(form.reserved);
    if (!Number.isFinite(onHand) || onHand < 0 || !Number.isFinite(reserved) || reserved < 0) {
      setError("On hand and reserved must be non-negative integers.");
      return;
    }
    if (reserved > onHand) {
      setError("Reserved cannot exceed on hand.");
      return;
    }

    setBusy(true);
    setError(null);
    setSuccess(null);

    const body = {
      warehouse_code: form.warehouseCode.trim().toUpperCase() || "MAIN",
      on_hand: Math.floor(onHand),
      reserved: Math.floor(reserved),
      reorder_level: Math.max(0, Math.floor(Number(form.reorderLevel) || 0)),
      safety_stock: Math.max(0, Math.floor(Number(form.safetyStock) || 0)),
      is_active: form.isActive,
    };

    try {
      if (editingId) {
        await updateAdminVariantInventory(editingId, body);
        setSuccess("Inventory updated.");
      } else {
        await createAdminVariantInventory(variantId, body);
        setSuccess("Inventory created.");
      }
      setEditingId(null);
      setForm(emptyForm());
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save inventory.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleReserve = async (row: AdminVariantInventory) => {
    const qty = Math.floor(Number(adjustQty) || 0);
    if (qty < 1) {
      setError("Enter a positive quantity to reserve.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await updateAdminVariantInventory(row.id, { reserve: qty });
      setSuccess(`Reserved ${qty} unit(s).`);
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to reserve stock.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleRelease = async (row: AdminVariantInventory) => {
    const qty = Math.floor(Number(adjustQty) || 0);
    if (qty < 1) {
      setError("Enter a positive quantity to release.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await updateAdminVariantInventory(row.id, { release: qty });
      setSuccess(`Released ${qty} unit(s).`);
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to release stock.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleToggleActive = async (row: AdminVariantInventory) => {
    setBusy(true);
    setError(null);
    try {
      await updateAdminVariantInventory(row.id, { is_active: !row.isActive });
      setSuccess(row.isActive ? "Inventory deactivated." : "Inventory activated.");
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to update inventory status.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async (row: AdminVariantInventory) => {
    if (!window.confirm(`Delete inventory for warehouse ${row.warehouseCode}?`)) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await deleteAdminVariantInventory(row.id);
      setSuccess("Inventory deleted.");
      if (editingId === row.id) {
        startCreate();
      }
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to delete inventory.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4 rounded-lg border border-zinc-200 bg-zinc-50/50 p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 className="text-sm font-semibold text-zinc-900">Inventory</h3>
          <p className="text-xs text-zinc-500">{variantLabel}</p>
        </div>
        {onClose ? (
          <button
            type="button"
            className="text-xs text-zinc-500 hover:text-zinc-800"
            onClick={onClose}
          >
            Close inventory
          </button>
        ) : null}
      </div>

      <p className="text-xs text-zinc-500">
        Standalone Inventory Engine — available = on hand − reserved. Movements and
        warehouses (DSM, MWANZA, …) come later; use warehouse codes now.
      </p>

      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
          {error}
        </div>
      ) : null}
      {success ? (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          {success}
        </div>
      ) : null}

      <div className="flex flex-wrap items-end gap-2">
        <div>
          <label className="admin-label" htmlFor="inv-adjust-qty">
            Reserve / release qty
          </label>
          <input
            id="inv-adjust-qty"
            type="number"
            min="1"
            className="admin-input mt-1 w-28"
            value={adjustQty}
            onChange={(event) => setAdjustQty(event.target.value)}
          />
        </div>
      </div>

      {isLoading ? (
        <p className="text-sm text-zinc-500">Loading inventory…</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
              <tr>
                <th className="px-3 py-2 font-medium">Warehouse</th>
                <th className="px-3 py-2 font-medium">On hand</th>
                <th className="px-3 py-2 font-medium">Reserved</th>
                <th className="px-3 py-2 font-medium">Available</th>
                <th className="px-3 py-2 font-medium">Reorder</th>
                <th className="px-3 py-2 font-medium">Status</th>
                <th className="px-3 py-2 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-3 py-5 text-center text-zinc-500">
                    No inventory rows yet. Create MAIN stock below.
                  </td>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.id} className="border-t border-zinc-100">
                    <td className="px-3 py-2 font-mono text-xs font-medium text-zinc-900">
                      {row.warehouseCode}
                    </td>
                    <td className="px-3 py-2">{row.onHand}</td>
                    <td className="px-3 py-2">{row.reserved}</td>
                    <td className="px-3 py-2 font-medium">{row.available}</td>
                    <td className="px-3 py-2">
                      <span
                        className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${
                          row.needsReorder
                            ? "bg-amber-50 text-amber-800"
                            : "bg-zinc-100 text-zinc-600"
                        }`}
                      >
                        {row.needsReorder ? `Low (≤${row.reorderLevel})` : `OK (${row.reorderLevel})`}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${
                          row.isActive
                            ? "bg-emerald-50 text-emerald-700"
                            : "bg-zinc-100 text-zinc-600"
                        }`}
                      >
                        {row.isActive ? "Active" : "Inactive"}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          className="text-xs font-medium text-zinc-700 hover:underline"
                          disabled={busy}
                          onClick={() => startEdit(row)}
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          className="text-xs font-medium text-zinc-700 hover:underline"
                          disabled={busy}
                          onClick={() => void handleReserve(row)}
                        >
                          Reserve
                        </button>
                        <button
                          type="button"
                          className="text-xs font-medium text-zinc-700 hover:underline"
                          disabled={busy}
                          onClick={() => void handleRelease(row)}
                        >
                          Release
                        </button>
                        <button
                          type="button"
                          className="text-xs font-medium text-zinc-700 hover:underline"
                          disabled={busy}
                          onClick={() => void handleToggleActive(row)}
                        >
                          {row.isActive ? "Deactivate" : "Activate"}
                        </button>
                        <button
                          type="button"
                          className="text-xs font-medium text-red-600 hover:underline"
                          disabled={busy}
                          onClick={() => void handleDelete(row)}
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      <div className="rounded-lg border border-zinc-200 bg-white p-3">
        <div className="flex items-center justify-between gap-2">
          <h4 className="text-sm font-semibold text-zinc-900">
            {editingId ? "Edit inventory" : "Create inventory"}
          </h4>
          {editingId ? (
            <button
              type="button"
              className="text-xs text-zinc-500 hover:text-zinc-800"
              onClick={startCreate}
            >
              Cancel edit
            </button>
          ) : null}
        </div>

        <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <label className="admin-label" htmlFor="inv-warehouse">
              Warehouse code
            </label>
            <input
              id="inv-warehouse"
              className="admin-input mt-1"
              value={form.warehouseCode}
              onChange={(event) =>
                setForm({ ...form, warehouseCode: event.target.value.toUpperCase() })
              }
              placeholder="MAIN"
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="inv-on-hand">
              On hand
            </label>
            <input
              id="inv-on-hand"
              type="number"
              min="0"
              className="admin-input mt-1"
              value={form.onHand}
              onChange={(event) => setForm({ ...form, onHand: event.target.value })}
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="inv-reserved">
              Reserved
            </label>
            <input
              id="inv-reserved"
              type="number"
              min="0"
              className="admin-input mt-1"
              value={form.reserved}
              onChange={(event) => setForm({ ...form, reserved: event.target.value })}
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="inv-reorder">
              Reorder level
            </label>
            <input
              id="inv-reorder"
              type="number"
              min="0"
              className="admin-input mt-1"
              value={form.reorderLevel}
              onChange={(event) =>
                setForm({ ...form, reorderLevel: event.target.value })
              }
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="inv-safety">
              Safety stock
            </label>
            <input
              id="inv-safety"
              type="number"
              min="0"
              className="admin-input mt-1"
              value={form.safetyStock}
              onChange={(event) =>
                setForm({ ...form, safetyStock: event.target.value })
              }
            />
          </div>
        </div>

        <label className="mt-3 flex items-center gap-2 text-sm text-zinc-700">
          <input
            type="checkbox"
            checked={form.isActive}
            onChange={(event) =>
              setForm({ ...form, isActive: event.target.checked })
            }
          />
          Active
        </label>

        <div className="mt-3">
          <button
            type="button"
            className="admin-btn-primary"
            disabled={busy}
            onClick={() => void handleSave()}
          >
            {busy ? "Saving…" : editingId ? "Update inventory" : "Create inventory"}
          </button>
        </div>
      </div>
    </div>
  );
}
