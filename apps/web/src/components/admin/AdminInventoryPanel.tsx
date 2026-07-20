"use client";

import { useCallback, useEffect, useState } from "react";
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
    <div className="space-y-6 p-4 md:p-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-zinc-50">Inventory Control</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Store-scoped stock operations over VariantInventory — receiving, counts, adjustments, ledger.
          </p>
        </div>
        <label className="text-sm text-zinc-400">
          Store
          <select
            value={storeId}
            onChange={(e) => setStoreId(e.target.value)}
            className="ml-2 rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
          >
            {stores.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
        </label>
      </div>

      {error ? (
        <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      <div className="flex flex-wrap gap-1 border-b border-zinc-800 pb-2">
        {tabs.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              tab === t.id ? "bg-[#c9a227]/20 text-[#e8c547]" : "text-zinc-400 hover:text-zinc-200"
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
            <div key={String(label)} className="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
              <p className="text-xs uppercase tracking-wide text-zinc-500">{label}</p>
              <p className="mt-1 text-2xl font-semibold text-zinc-50">{value}</p>
            </div>
          ))}
        </div>
      ) : null}

      {tab === "stock" ? (
        <div className="overflow-x-auto rounded-lg border border-zinc-800">
          <table className="min-w-full text-left text-sm text-zinc-300">
            <thead className="bg-zinc-900/80 text-xs uppercase text-zinc-500">
              <tr>
                <th className="px-3 py-2">SKU</th>
                <th className="px-3 py-2">Product</th>
                <th className="px-3 py-2">Available</th>
                <th className="px-3 py-2">Damaged</th>
                <th className="px-3 py-2">Reorder</th>
              </tr>
            </thead>
            <tbody>
              {stock.map((row) => (
                <tr key={row.id} className="border-t border-zinc-800">
                  <td className="px-3 py-2 font-mono text-xs">{row.sku}</td>
                  <td className="px-3 py-2">{row.product_name}</td>
                  <td className="px-3 py-2">{row.available}</td>
                  <td className="px-3 py-2">{row.damaged}</td>
                  <td className="px-3 py-2">{row.needs_reorder ? "Low" : "OK"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {tab === "movements" ? (
        <div className="space-y-4">
          <form
            onSubmit={onAdjust}
            className="grid gap-2 rounded-lg border border-zinc-800 bg-zinc-900/40 p-4 md:grid-cols-5"
          >
            <h2 className="md:col-span-5 text-sm font-medium text-zinc-200">Stock adjustment</h2>
            <input
              required
              value={adjust.product_variant_id}
              onChange={(e) => setAdjust((a) => ({ ...a, product_variant_id: e.target.value }))}
              placeholder="Variant UUID"
              className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
            />
            <input
              required
              type="number"
              value={adjust.quantity_change}
              onChange={(e) => setAdjust((a) => ({ ...a, quantity_change: e.target.value }))}
              className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
            />
            <select
              value={adjust.kind}
              onChange={(e) => setAdjust((a) => ({ ...a, kind: e.target.value }))}
              className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
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
              className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
            />
            <button
              type="submit"
              disabled={busy}
              className="rounded-md bg-[#c9a227] px-3 py-1.5 text-sm font-medium text-zinc-950 disabled:opacity-50"
            >
              Apply
            </button>
          </form>
          <div className="overflow-x-auto rounded-lg border border-zinc-800">
            <table className="min-w-full text-left text-sm text-zinc-300">
              <thead className="bg-zinc-900/80 text-xs uppercase text-zinc-500">
                <tr>
                  <th className="px-3 py-2">Type</th>
                  <th className="px-3 py-2">SKU</th>
                  <th className="px-3 py-2">Change</th>
                  <th className="px-3 py-2">After</th>
                  <th className="px-3 py-2">Reason</th>
                </tr>
              </thead>
              <tbody>
                {movements.map((m) => (
                  <tr key={m.id} className="border-t border-zinc-800">
                    <td className="px-3 py-2 capitalize">{m.movement_type}</td>
                    <td className="px-3 py-2 font-mono text-xs">{m.sku}</td>
                    <td className="px-3 py-2">{m.quantity_change}</td>
                    <td className="px-3 py-2">{m.quantity_after}</td>
                    <td className="px-3 py-2 text-zinc-500">{m.reason}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {tab === "counts" ? (
        <div className="space-y-3">
          <button
            type="button"
            disabled={busy || !storeId}
            onClick={() => void onStartCount()}
            className="rounded-md bg-[#c9a227] px-3 py-1.5 text-sm font-medium text-zinc-950 disabled:opacity-50"
          >
            Start full store count
          </button>
          <ul className="space-y-2">
            {counts.map((c) => (
              <li key={c.id} className="rounded-md border border-zinc-800 px-3 py-2 text-sm text-zinc-300">
                <span className="font-mono text-xs text-[#e8c547]">{c.count_number}</span> · {c.status} ·{" "}
                {c.scope}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {tab === "valuation" && valuation ? (
        <div className="space-y-3">
          <p className="text-sm text-zinc-400">
            Total cost value:{" "}
            <span className="text-zinc-100">
              {Number(valuation.summary.total_cost_value ?? 0).toLocaleString()} TZS
            </span>{" "}
            · {valuation.summary.sku_count} SKUs
          </p>
          <div className="overflow-x-auto rounded-lg border border-zinc-800">
            <table className="min-w-full text-left text-sm text-zinc-300">
              <thead className="bg-zinc-900/80 text-xs uppercase text-zinc-500">
                <tr>
                  <th className="px-3 py-2">Product</th>
                  <th className="px-3 py-2">Qty</th>
                  <th className="px-3 py-2">Unit cost</th>
                  <th className="px-3 py-2">Value</th>
                </tr>
              </thead>
              <tbody>
                {valuation.rows.slice(0, 50).map((row) => (
                  <tr key={String(row.variant_inventory_id)} className="border-t border-zinc-800">
                    <td className="px-3 py-2">{String(row.product_name ?? row.sku)}</td>
                    <td className="px-3 py-2">{String(row.stock_quantity)}</td>
                    <td className="px-3 py-2">{String(row.unit_cost)}</td>
                    <td className="px-3 py-2">{String(row.cost_value)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {tab === "low" ? (
        <ul className="space-y-2">
          {low.map((row) => (
            <li
              key={String(row.variant_inventory_id)}
              className="rounded-md border border-zinc-800 px-3 py-2 text-sm text-zinc-300"
            >
              {String(row.product_name ?? row.sku)} · available {String(row.available)} / min{" "}
              {String(row.reorder_level)} · {String(row.status)}
            </li>
          ))}
          {low.length === 0 ? <li className="text-sm text-zinc-500">No low-stock items.</li> : null}
        </ul>
      ) : null}
    </div>
  );
}
