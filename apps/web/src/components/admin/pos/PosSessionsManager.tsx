"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { fetchPosSessions, fetchPosStores, type PosSession, type PosStore } from "@/lib/api/admin-pos";

function money(value: string | number | null | undefined): string {
  const n = Number(value ?? 0);
  return `${n.toLocaleString("en-TZ", { minimumFractionDigits: 0, maximumFractionDigits: 0 })} TZS`;
}

export function PosSessionsManager() {
  const [stores, setStores] = useState<PosStore[]>([]);
  const [sessions, setSessions] = useState<PosSession[]>([]);
  const [storeId, setStoreId] = useState("");
  const [status, setStatus] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setBusy(true);
    setError(null);
    try {
      const [storeList, rows] = await Promise.all([
        fetchPosStores(),
        fetchPosSessions({
          store_id: storeId || undefined,
          status: status || undefined,
        }),
      ]);
      setStores(storeList);
      setSessions(rows);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load sessions.");
    } finally {
      setBusy(false);
    }
  }, [storeId, status]);

  useEffect(() => {
    load().catch(() => undefined);
  }, [load]);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3 border-b border-zinc-200 pb-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900">POS Sessions</h1>
          <p className="mt-1 text-sm text-zinc-500">Manager view · float, sales, variance</p>
        </div>
        <Link href="/admin/pos" className="rounded-md border border-zinc-300 px-3 py-1.5 text-sm text-zinc-700">
          Back to POS
        </Link>
      </div>

      {error ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>
      ) : null}

      <div className="flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-zinc-700">Store</span>
          <select
            className="min-w-[200px] rounded-md border border-zinc-300 px-3 py-2"
            value={storeId}
            onChange={(e) => setStoreId(e.target.value)}
          >
            <option value="">All assigned</option>
            {stores.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-zinc-700">Status</span>
          <select
            className="rounded-md border border-zinc-300 px-3 py-2"
            value={status}
            onChange={(e) => setStatus(e.target.value)}
          >
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="closed">Closed</option>
          </select>
        </label>
        <button
          type="button"
          disabled={busy}
          onClick={() => load()}
          className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          Refresh
        </button>
      </div>

      <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
        <table className="min-w-full text-left text-sm">
          <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
            <tr>
              <th className="px-3 py-2 font-medium">Store</th>
              <th className="px-3 py-2 font-medium">Cashier</th>
              <th className="px-3 py-2 font-medium">Status</th>
              <th className="px-3 py-2 font-medium">Opening</th>
              <th className="px-3 py-2 font-medium">Expected</th>
              <th className="px-3 py-2 font-medium">Closing</th>
              <th className="px-3 py-2 font-medium">Variance</th>
              <th className="px-3 py-2 font-medium">Sales</th>
              <th className="px-3 py-2 font-medium">Txns</th>
            </tr>
          </thead>
          <tbody>
            {sessions.map((row) => {
              const summary = row.summary;
              const variance = summary?.variance_amount ?? row.variance_amount;
              const varianceType = summary?.variance_type ?? row.variance_type;
              return (
                <tr key={row.id} className="border-b border-zinc-100">
                  <td className="px-3 py-2">{row.store?.name ?? "—"}</td>
                  <td className="px-3 py-2">{row.cashier?.name ?? "—"}</td>
                  <td className="px-3 py-2 uppercase">{row.status}</td>
                  <td className="px-3 py-2">{money(summary?.opening_float ?? row.opening_float)}</td>
                  <td className="px-3 py-2">{money(summary?.expected_cash ?? row.expected_cash)}</td>
                  <td className="px-3 py-2">{money(summary?.closing_cash ?? row.closing_cash)}</td>
                  <td className="px-3 py-2">
                    {variance != null ? (
                      <span
                        className={
                          varianceType === "short"
                            ? "text-red-700"
                            : varianceType === "over"
                              ? "text-amber-700"
                              : "text-emerald-700"
                        }
                      >
                        {money(variance)} {varianceType ? `(${varianceType})` : ""}
                      </span>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="px-3 py-2">{money(summary?.total_sales)}</td>
                  <td className="px-3 py-2">{summary?.transaction_count ?? row.transaction_count ?? 0}</td>
                </tr>
              );
            })}
            {sessions.length === 0 ? (
              <tr>
                <td colSpan={9} className="px-3 py-8 text-center text-zinc-500">
                  {busy ? "Loading…" : "No sessions found."}
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  );
}
