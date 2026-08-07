"use client";

import { useCallback, useEffect, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import {
  AdminInventoryApiError,
  createInventoryAdjustment,
  createInventoryCount,
  fetchInventoryCounts,
  fetchInventoryDashboard,
  fetchInventoryLowStock,
  fetchInventoryMovements,
  fetchInventoryStock,
  fetchInventoryValuation,
  type InventoryCountSession,
  type InventoryDashboard,
  type InventoryMovement,
  type InventoryStockRow,
} from "@/lib/api/admin-inventory";
import { fetchPosStores, type PosStore } from "@/lib/api/admin-pos";

type Tab = "dashboard" | "stock" | "movements" | "counts" | "valuation" | "low";

export function AdminInventoryPanel() {
  const [tab, setTab] = useState<Tab>("dashboard");
  const [stores, setStores] = useState<PosStore[]>([]);
  const [storeId, setStoreId] = useState("");
  const [dashboard, setDashboard] = useState<InventoryDashboard | null>(null);
  const [stock, setStock] = useState<InventoryStockRow[]>([]);
  const [movements, setMovements] = useState<InventoryMovement[]>([]);
  const [counts, setCounts] = useState<InventoryCountSession[]>([]);
  const [valuation, setValuation] = useState<{
    summary: Record<string, number>;
    rows: Array<Record<string, unknown>>;
  } | null>(null);
  const [low, setLow] = useState<Array<Record<string, unknown>>>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [adjust, setAdjust] = useState({
    product_variant_id: "",
    quantity_change: "-1",
    reason: "",
    kind: "adjustment",
  });

  const reload = useCallback(async () => {
    setError(null);
    try {
      const sid = storeId || undefined;
      if (tab === "dashboard") setDashboard(await fetchInventoryDashboard(sid));
      if (tab === "stock") setStock(await fetchInventoryStock(sid));
      if (tab === "movements") setMovements(await fetchInventoryMovements(sid));
      if (tab === "counts") setCounts(await fetchInventoryCounts(sid));
      if (tab === "valuation") setValuation(await fetchInventoryValuation(sid));
      if (tab === "low") setLow(await fetchInventoryLowStock(sid));
    } catch (err) {
      setError(err instanceof AdminInventoryApiError ? err.message : "Unable to load inventory.");
    }
  }, [storeId, tab]);

  useEffect(() => {
    void fetchPosStores()
      .then((list) => {
        setStores(list);
        if (!storeId && list[0]) setStoreId(list[0].id);
      })
      .catch(() => undefined);
  }, [storeId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const onAdjust = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!storeId) return;
    setBusy(true);
    setError(null);
    try {
      await createInventoryAdjustment({
        store_id: storeId,
        product_variant_id: adjust.product_variant_id,
        quantity_change: Number(adjust.quantity_change),
        reason: adjust.reason.trim(),
        kind: adjust.kind,
      });
      setAdjust({ product_variant_id: "", quantity_change: "-1", reason: "", kind: "adjustment" });
      setTab("movements");
      await reload();
    } catch (err) {
      setError(err instanceof AdminInventoryApiError ? err.message : "Adjustment failed.");
    } finally {
      setBusy(false);
    }
  };

  const onStartCount = async () => {
    if (!storeId) return;
    setBusy(true);
    try {
      await createInventoryCount({ store_id: storeId, scope: "full" });
      setTab("counts");
      await reload();
    } catch (err) {
      setError(err instanceof AdminInventoryApiError ? err.message : "Count create failed.");
    } finally {
      setBusy(false);
    }
  };

  const tabs: Array<{ id: Tab; label: string }> = [
    { id: "dashboard", label: "Dashboard" },
    { id: "stock", label: "Stock Levels" },
    { id: "movements", label: "Movements" },
    { id: "counts", label: "Stock Count" },
    { id: "valuation", label: "Valuation" },
    { id: "low", label: "Low Stock" },
  ];

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Inventory Control"
        description="Store-scoped stock operations over VariantInventory — receiving, counts, adjustments, ledger."
        actions={
          <label className="admin-label">
            Store
            <select
              value={storeId}
              onChange={(e) => setStoreId(e.target.value)}
              className="admin-input mt-1 min-w-[160px]"
            >
              {stores.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </label>
        }
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <div className="flex flex-wrap gap-1 border-b border-zinc-200 pb-2">
        {tabs.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
              tab === t.id
                ? "bg-[#c9a227]/15 text-[#8b6914] ring-1 ring-[#c9a227]/40"
                : "text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === "dashboard" && dashboard ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {[
            ["SKUs", dashboard.sku_count],
            ["Sellable units", dashboard.sellable_units],
            ["Damaged", dashboard.damaged_units],
            ["Low stock SKUs", dashboard.low_stock_skus],
            ["Inventory value", `${dashboard.inventory_value.toLocaleString()} TZS`],
            ["Open counts", dashboard.open_counts],
          ].map(([label, value]) => (
            <div key={String(label)} className="admin-stat-card px-4 py-4 sm:px-5">
              <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600">
                {label}
              </p>
              <p className="mt-2 text-xl font-bold text-zinc-900">{value}</p>
            </div>
          ))}
        </div>
      ) : null}

      {tab === "stock" ? (
        <div className="admin-card overflow-hidden">
          <div className="admin-table-scroll">
            <table className="admin-table min-w-full">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Product</th>
                  <th>Available</th>
                  <th>Damaged</th>
                  <th>Reorder</th>
                </tr>
              </thead>
              <tbody>
                {stock.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="!p-0">
                      <AdminEmptyState title="No stock rows yet" />
                    </td>
                  </tr>
                ) : (
                  stock.map((row) => (
                    <tr key={row.id}>
                      <td className="font-mono text-xs">{row.sku}</td>
                      <td className="admin-table-primary">{row.product_name}</td>
                      <td>{row.available}</td>
                      <td>{row.damaged}</td>
                      <td>
                        <span
                          className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                            row.needs_reorder
                              ? "bg-amber-50 text-amber-800 ring-1 ring-amber-200"
                              : "bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200"
                          }`}
                        >
                          {row.needs_reorder ? "Low" : "OK"}
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {tab === "movements" ? (
        <div className="space-y-4">
          <form onSubmit={onAdjust} className="admin-card grid gap-2 p-4 md:grid-cols-5">
            <h2 className="md:col-span-5 text-sm font-semibold text-zinc-900">Stock adjustment</h2>
            <input
              required
              value={adjust.product_variant_id}
              onChange={(e) => setAdjust((a) => ({ ...a, product_variant_id: e.target.value }))}
              placeholder="Variant UUID"
              className="admin-input"
            />
            <input
              required
              type="number"
              value={adjust.quantity_change}
              onChange={(e) => setAdjust((a) => ({ ...a, quantity_change: e.target.value }))}
              className="admin-input"
            />
            <select
              value={adjust.kind}
              onChange={(e) => setAdjust((a) => ({ ...a, kind: e.target.value }))}
              className="admin-input"
            >
              <option value="adjustment">Adjustment</option>
              <option value="correction">Correction</option>
              <option value="damage">Damage</option>
              <option value="found">Found</option>
            </select>
            <input
              required
              value={adjust.reason}
              onChange={(e) => setAdjust((a) => ({ ...a, reason: e.target.value }))}
              placeholder="Reason (required)"
              className="admin-input"
            />
            <button type="submit" disabled={busy} className="admin-btn-primary disabled:opacity-50">
              Apply
            </button>
          </form>
          <div className="admin-card overflow-hidden">
            <div className="admin-table-scroll">
              <table className="admin-table min-w-full">
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>SKU</th>
                    <th>Change</th>
                    <th>After</th>
                    <th>Reason</th>
                  </tr>
                </thead>
                <tbody>
                  {movements.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="!p-0">
                        <AdminEmptyState title="No movements yet" />
                      </td>
                    </tr>
                  ) : (
                    movements.map((m) => (
                      <tr key={m.id}>
                        <td className="capitalize">{m.movement_type}</td>
                        <td className="font-mono text-xs">{m.sku}</td>
                        <td>{m.quantity_change}</td>
                        <td>{m.quantity_after}</td>
                        <td className="text-zinc-600">{m.reason}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ) : null}

      {tab === "counts" ? (
        <div className="space-y-3">
          <button
            type="button"
            disabled={busy || !storeId}
            onClick={() => void onStartCount()}
            className="admin-btn-primary disabled:opacity-50"
          >
            Start full store count
          </button>
          {counts.length === 0 ? (
            <AdminEmptyState title="No stock counts yet" />
          ) : (
            <ul className="space-y-2">
              {counts.map((c) => (
                <li
                  key={c.id}
                  className="admin-card px-3 py-2 text-sm text-zinc-700"
                >
                  <span className="font-mono text-xs font-medium text-[#8b6914]">
                    {c.count_number}
                  </span>{" "}
                  · {c.status} · {c.scope}
                </li>
              ))}
            </ul>
          )}
        </div>
      ) : null}

      {tab === "valuation" && valuation ? (
        <div className="space-y-3">
          <p className="text-sm text-zinc-600">
            Total cost value:{" "}
            <span className="font-semibold text-zinc-900">
              {Number(valuation.summary.total_cost_value ?? 0).toLocaleString()} TZS
            </span>{" "}
            · {valuation.summary.sku_count} SKUs
          </p>
          <div className="admin-card overflow-hidden">
            <div className="admin-table-scroll">
              <table className="admin-table min-w-full">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit cost</th>
                    <th>Value</th>
                  </tr>
                </thead>
                <tbody>
                  {valuation.rows.slice(0, 50).map((row) => (
                    <tr key={String(row.variant_inventory_id)}>
                      <td className="admin-table-primary">
                        {String(row.product_name ?? row.sku)}
                      </td>
                      <td>{String(row.stock_quantity)}</td>
                      <td>{String(row.unit_cost)}</td>
                      <td>{String(row.cost_value)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ) : null}

      {tab === "low" ? (
        low.length === 0 ? (
          <AdminEmptyState title="No low-stock items" />
        ) : (
          <ul className="space-y-2">
            {low.map((row) => (
              <li
                key={String(row.variant_inventory_id)}
                className="admin-card px-3 py-2 text-sm text-zinc-700"
              >
                {String(row.product_name ?? row.sku)} · available {String(row.available)} / min{" "}
                {String(row.reorder_level)} · {String(row.status)}
              </li>
            ))}
          </ul>
        )
      ) : null}
    </div>
  );
}
