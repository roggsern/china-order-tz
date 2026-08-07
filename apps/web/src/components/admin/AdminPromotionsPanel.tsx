"use client";

import { useCallback, useEffect, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import {
  AdminPromotionsApiError,
  createAdminPromotion,
  fetchAdminPromotions,
  fetchAdminPromotionUsage,
  updateAdminPromotionStatus,
  type AdminPromotion,
} from "@/lib/api/admin-promotions";

function statusBadgeClass(status: string): string {
  if (status === "active") return "bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200";
  if (status === "inactive") return "bg-amber-50 text-amber-800 ring-1 ring-amber-200";
  if (status === "draft") return "bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200";
  return "bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200";
}

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
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Promotions"
        description="Coupons and automatic discounts resolved by the Promotion Engine at checkout."
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <form onSubmit={onCreate} className="admin-card grid gap-3 p-4 md:grid-cols-3">
        <input
          required
          value={form.name}
          onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
          placeholder="Name"
          className="admin-input"
        />
        <input
          value={form.code}
          onChange={(e) => setForm((f) => ({ ...f, code: e.target.value.toUpperCase() }))}
          placeholder="Code (coupons)"
          className="admin-input"
        />
        <select
          value={form.type}
          onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
          className="admin-input"
        >
          <option value="coupon">Coupon</option>
          <option value="automatic">Automatic</option>
        </select>
        <select
          value={form.discount_type}
          onChange={(e) => setForm((f) => ({ ...f, discount_type: e.target.value }))}
          className="admin-input"
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
          className="admin-input"
        />
        <input
          value={form.minimum_order_amount}
          onChange={(e) => setForm((f) => ({ ...f, minimum_order_amount: e.target.value }))}
          placeholder="Min order amount"
          className="admin-input"
        />
        <button
          type="submit"
          disabled={busy}
          className="admin-btn-primary md:col-span-3 disabled:opacity-50"
        >
          Create promotion
        </button>
      </form>

      <div className="admin-card overflow-hidden">
        <div className="admin-table-scroll">
          <table className="admin-table min-w-full">
            <thead>
              <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Type</th>
                <th>Discount</th>
                <th>Status</th>
                <th>Uses</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={7} className="text-zinc-600">
                    Loading…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={7} className="!p-0">
                    <AdminEmptyState title="No promotions yet" />
                  </td>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.id}>
                    <td className="admin-table-primary">{row.name}</td>
                    <td className="text-zinc-600">{row.code ?? "—"}</td>
                    <td className="text-zinc-600">{row.type}</td>
                    <td>
                      {row.discount_type} / {row.value}
                    </td>
                    <td>
                      <span
                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusBadgeClass(row.status)}`}
                      >
                        {row.status}
                      </span>
                    </td>
                    <td className="text-zinc-600">{row.usages_count ?? 0}</td>
                    <td>
                      <div className="flex flex-wrap gap-1">
                        {row.status !== "active" ? (
                          <button
                            type="button"
                            onClick={() =>
                              void updateAdminPromotionStatus(row.id, "active").then(reload)
                            }
                            className="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800"
                          >
                            Activate
                          </button>
                        ) : (
                          <button
                            type="button"
                            onClick={() =>
                              void updateAdminPromotionStatus(row.id, "inactive").then(reload)
                            }
                            className="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800"
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
                          className="admin-btn-secondary !px-2 !py-1 text-xs"
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
      </div>

      {selectedId ? (
        <section className="admin-card p-4">
          <h2 className="text-sm font-semibold text-zinc-900">Usage · {selectedId}</h2>
          <pre className="mt-2 max-h-64 overflow-auto rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-600">
            {JSON.stringify(usage, null, 2)}
          </pre>
        </section>
      ) : null}
    </div>
  );
}
