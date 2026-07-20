"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminPromotionsApiError,
  createAdminPromotion,
  fetchAdminPromotions,
  fetchAdminPromotionUsage,
  updateAdminPromotionStatus,
  type AdminPromotion,
} from "@/lib/api/admin-promotions";

export function AdminPromotionsPanel() {
  const [rows, setRows] = useState<AdminPromotion[]>([]);
  const [usage, setUsage] = useState<unknown[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    name: "",
    code: "",
    type: "coupon",
    discount_type: "percentage",
    value: "10",
    minimum_order_amount: "",
    status: "draft",
  });

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setRows(await fetchAdminPromotions());
    } catch (err) {
      setError(err instanceof AdminPromotionsApiError ? err.message : "Unable to load promotions.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const onCreate = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await createAdminPromotion({
        name: form.name.trim(),
        code: form.type === "coupon" ? form.code.trim().toUpperCase() : null,
        type: form.type,
        discount_type: form.discount_type,
        value: Number(form.value),
        minimum_order_amount: form.minimum_order_amount
          ? Number(form.minimum_order_amount)
          : null,
        status: form.status,
        rules: [],
      });
      setForm({
        name: "",
        code: "",
        type: "coupon",
        discount_type: "percentage",
        value: "10",
        minimum_order_amount: "",
        status: "draft",
      });
      await reload();
    } catch (err) {
      setError(err instanceof AdminPromotionsApiError ? err.message : "Unable to create.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-6 p-4 md:p-6">
      <div>
        <h1 className="text-xl font-semibold text-zinc-50">Promotions</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Coupons and automatic discounts resolved by the Promotion Engine at checkout.
        </p>
      </div>

      {error ? (
        <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      <form
        onSubmit={onCreate}
        className="grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900/40 p-4 md:grid-cols-3"
      >
        <input
          required
          value={form.name}
          onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
          placeholder="Name"
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
        />
        <input
          value={form.code}
          onChange={(e) => setForm((f) => ({ ...f, code: e.target.value.toUpperCase() }))}
          placeholder="Code (coupons)"
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
        />
        <select
          value={form.type}
          onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
        >
          <option value="coupon">Coupon</option>
          <option value="automatic">Automatic</option>
        </select>
        <select
          value={form.discount_type}
          onChange={(e) => setForm((f) => ({ ...f, discount_type: e.target.value }))}
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
        >
          <option value="percentage">Percentage</option>
          <option value="fixed_amount">Fixed amount</option>
          <option value="free_shipping">Free shipping</option>
        </select>
        <input
          required
          value={form.value}
          onChange={(e) => setForm((f) => ({ ...f, value: e.target.value }))}
          placeholder="Value"
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
        />
        <input
          value={form.minimum_order_amount}
          onChange={(e) => setForm((f) => ({ ...f, minimum_order_amount: e.target.value }))}
          placeholder="Min order amount"
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
        />
        <button
          type="submit"
          disabled={busy}
          className="rounded-md bg-[#c9a227] px-3 py-2 text-sm font-semibold text-zinc-950 md:col-span-3"
        >
          Create promotion
        </button>
      </form>

      <div className="overflow-x-auto rounded-lg border border-zinc-800">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-zinc-900 text-[11px] uppercase tracking-wider text-zinc-500">
            <tr>
              <th className="px-3 py-2.5">Name</th>
              <th className="px-3 py-2.5">Code</th>
              <th className="px-3 py-2.5">Type</th>
              <th className="px-3 py-2.5">Discount</th>
              <th className="px-3 py-2.5">Status</th>
              <th className="px-3 py-2.5">Uses</th>
              <th className="px-3 py-2.5">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan={7} className="px-3 py-6 text-zinc-500">
                  Loading…
                </td>
              </tr>
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={7} className="px-3 py-6 text-zinc-500">
                  No promotions yet.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.id} className="border-t border-zinc-800/80">
                  <td className="px-3 py-2.5 text-zinc-100">{row.name}</td>
                  <td className="px-3 py-2.5 text-zinc-400">{row.code ?? "—"}</td>
                  <td className="px-3 py-2.5 text-zinc-400">{row.type}</td>
                  <td className="px-3 py-2.5 text-zinc-300">
                    {row.discount_type} / {row.value}
                  </td>
                  <td className="px-3 py-2.5 capitalize text-zinc-300">{row.status}</td>
                  <td className="px-3 py-2.5 text-zinc-400">{row.usages_count ?? 0}</td>
                  <td className="px-3 py-2.5">
                    <div className="flex flex-wrap gap-1">
                      {row.status !== "active" ? (
                        <button
                          type="button"
                          onClick={() =>
                            void updateAdminPromotionStatus(row.id, "active").then(reload)
                          }
                          className="rounded border border-zinc-700 px-2 py-1 text-xs text-emerald-300"
                        >
                          Activate
                        </button>
                      ) : (
                        <button
                          type="button"
                          onClick={() =>
                            void updateAdminPromotionStatus(row.id, "inactive").then(reload)
                          }
                          className="rounded border border-zinc-700 px-2 py-1 text-xs text-amber-300"
                        >
                          Deactivate
                        </button>
                      )}
                      <button
                        type="button"
                        onClick={() =>
                          void fetchAdminPromotionUsage(row.id).then((u) => {
                            setSelectedId(row.id);
                            setUsage(u);
                          })
                        }
                        className="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300"
                      >
                        Usage
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {selectedId ? (
        <section className="rounded-lg border border-zinc-800 p-4">
          <h2 className="text-sm font-semibold text-zinc-100">Usage · {selectedId}</h2>
          <pre className="mt-2 max-h-64 overflow-auto text-xs text-zinc-400">
            {JSON.stringify(usage, null, 2)}
          </pre>
        </section>
      ) : null}
    </div>
  );
}
