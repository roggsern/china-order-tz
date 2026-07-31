"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  AdminChinaProcurementApiError,
  PROCUREMENT_SECTIONS,
  canManageChinaProcurement,
  canViewChinaProcurement,
  completeChinaProcurement,
  fetchChinaProcurementRequirement,
  fetchChinaProcurementRequirements,
  markChinaProcurementPurchased,
  startChinaProcurementQc,
  type ChinaProcurementRequirement,
} from "@/lib/api/admin-china-procurement";

function formatVariantAttributes(attributes?: Record<string, unknown>): string {
  if (!attributes) return "—";
  const parts = Object.entries(attributes)
    .filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== "")
    .map(([key, value]) => `${key}: ${String(value)}`);
  return parts.length > 0 ? parts.join(" · ") : "—";
}

export function AdminChinaProcurementBoard() {
  const { permissions } = useAdminPermissions();
  const canView = canViewChinaProcurement(permissions);
  const canManage = canManageChinaProcurement(permissions);

  const [section, setSection] = useState<string>("pending");
  const [rows, setRows] = useState<ChinaProcurementRequirement[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<ChinaProcurementRequirement | null>(null);
  const [purchaseQty, setPurchaseQty] = useState("1");
  const [busy, setBusy] = useState(false);

  const activeStatus = useMemo(
    () => PROCUREMENT_SECTIONS.find((item) => item.id === section)?.status,
    [section],
  );

  const reload = useCallback(async () => {
    if (!canView) {
      setRows([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const page = await fetchChinaProcurementRequirements({
        status: activeStatus,
        perPage: 50,
      });
      setRows(page.items);
    } catch (err) {
      setError(err instanceof AdminChinaProcurementApiError ? err.message : "Unable to load board.");
    } finally {
      setLoading(false);
    }
  }, [activeStatus, canView]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    if (!selectedId) {
      setDetail(null);
      return;
    }

    void (async () => {
      try {
        const data = await fetchChinaProcurementRequirement(selectedId);
        setDetail(data);
        setPurchaseQty(String(Math.max(1, data.quantity_remaining || 1)));
      } catch (err) {
        setError(err instanceof AdminChinaProcurementApiError ? err.message : "Unable to load detail.");
      }
    })();
  }, [selectedId]);

  async function handleMarkPurchased() {
    if (!selectedId || !canManage) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await markChinaProcurementPurchased(selectedId, Number(purchaseQty));
      setDetail(updated);
      await reload();
    } catch (err) {
      setError(err instanceof AdminChinaProcurementApiError ? err.message : "Unable to mark purchased.");
    } finally {
      setBusy(false);
    }
  }

  async function handleStartQc() {
    if (!selectedId || !canManage) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await startChinaProcurementQc(selectedId);
      setDetail(updated);
      await reload();
    } catch (err) {
      setError(err instanceof AdminChinaProcurementApiError ? err.message : "Unable to start QC.");
    } finally {
      setBusy(false);
    }
  }

  async function handleComplete() {
    if (!selectedId || !canManage) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await completeChinaProcurement(selectedId);
      setDetail(updated);
      await reload();
    } catch (err) {
      setError(err instanceof AdminChinaProcurementApiError ? err.message : "Unable to complete requirement.");
    } finally {
      setBusy(false);
    }
  }

  if (!canView) {
    return (
      <div className="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-600">
        You do not have permission to view the China procurement board.
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">China Operations</p>
          <h1 className="mt-1 text-2xl font-bold text-zinc-900">Procurement Board</h1>
          <p className="mt-2 max-w-2xl text-sm text-zinc-500">
            Aggregated customer demand grouped by product and variant for supplier purchasing.
          </p>
        </div>
        <Link href="/admin/analytics/china" className="text-sm font-semibold text-[#8b6914] hover:underline">
          View China analytics
        </Link>
      </header>

      <div className="flex flex-wrap gap-2">
        {PROCUREMENT_SECTIONS.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => {
              setSection(item.id);
              setSelectedId(null);
            }}
            className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
              section === item.id
                ? "bg-[#c9a227] text-white"
                : "bg-zinc-100 text-zinc-600 hover:bg-zinc-200"
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div className="overflow-hidden rounded-2xl border border-zinc-200 bg-white">
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-zinc-100 bg-zinc-50/80 text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                  <th className="px-4 py-3">Product</th>
                  <th className="px-4 py-3">Variant</th>
                  <th className="px-4 py-3">Required</th>
                  <th className="px-4 py-3">Purchased</th>
                  <th className="px-4 py-3">Remaining</th>
                  <th className="px-4 py-3">Supplier</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-zinc-500">
                      Loading procurement requirements…
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-zinc-500">
                      No requirements in this section.
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => (
                    <tr
                      key={row.id}
                      className={`cursor-pointer border-t border-zinc-100 transition hover:bg-[#c9a227]/5 ${
                        selectedId === row.id ? "bg-[#c9a227]/10" : ""
                      }`}
                      onClick={() => setSelectedId(row.id)}
                    >
                      <td className="px-4 py-3 font-medium text-zinc-900">{row.product?.name ?? "—"}</td>
                      <td className="px-4 py-3 text-zinc-600">
                        {row.variant?.name ?? formatVariantAttributes(row.variant_attributes)}
                      </td>
                      <td className="px-4 py-3 tabular-nums">{row.quantity_required}</td>
                      <td className="px-4 py-3 tabular-nums">{row.quantity_purchased}</td>
                      <td className="px-4 py-3 tabular-nums">{row.quantity_remaining}</td>
                      <td className="px-4 py-3 text-zinc-600">{row.supplier?.name ?? "—"}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        <aside className="rounded-2xl border border-zinc-200 bg-white p-5">
          {!detail ? (
            <p className="text-sm text-zinc-500">Select a procurement line to view linked orders and actions.</p>
          ) : (
            <div className="space-y-5">
              <div>
                <h2 className="text-lg font-bold text-zinc-900">{detail.product?.name}</h2>
                <p className="mt-1 text-sm text-zinc-500">
                  {detail.variant?.name ?? formatVariantAttributes(detail.variant_attributes)}
                </p>
                <p className="mt-2 text-sm text-zinc-600">
                  Required {detail.quantity_required} · Purchased {detail.quantity_purchased} · Remaining{" "}
                  {detail.quantity_remaining}
                </p>
              </div>

              <div>
                <h3 className="text-sm font-semibold text-zinc-900">Linked orders</h3>
                <ul className="mt-2 space-y-2">
                  {(detail.linked_orders ?? []).length === 0 ? (
                    <li className="text-sm text-zinc-500">No linked orders.</li>
                  ) : (
                    detail.linked_orders?.map((order) => (
                      <li key={order.order_id} className="rounded-xl border border-zinc-100 px-3 py-2 text-sm">
                        <div className="font-semibold text-zinc-900">{order.order_number ?? order.order_id}</div>
                        <div className="text-zinc-500">Qty {order.quantity}</div>
                      </li>
                    ))
                  )}
                </ul>
              </div>

              {canManage ? (
                <div className="space-y-3 border-t border-zinc-100 pt-4">
                  <label className="block text-sm font-semibold text-zinc-900">
                    Mark purchased quantity
                    <input
                      type="number"
                      min={1}
                      value={purchaseQty}
                      onChange={(event) => setPurchaseQty(event.target.value)}
                      className="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2"
                    />
                  </label>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void handleMarkPurchased()}
                    className="w-full rounded-xl bg-[#c9a227] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                  >
                    Mark purchased
                  </button>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void handleStartQc()}
                    className="w-full rounded-xl border border-zinc-200 px-4 py-2 text-sm font-semibold text-zinc-800 disabled:opacity-60"
                  >
                    Start QC
                  </button>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void handleComplete()}
                    className="w-full rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 disabled:opacity-60"
                  >
                    Complete
                  </button>
                </div>
              ) : null}
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}
