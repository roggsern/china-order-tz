"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  fetchPosReturnReasons,
  fetchPosReturns,
  lookupPosReturnOrder,
  processPosReturn,
  searchPosReturns,
  type PosReturnRecord,
  type PosReturnReason,
  type PosReturnSearchRow,
} from "@/lib/api/admin-pos-returns";

function money(value: string | number | null | undefined): string {
  return `${Number(value ?? 0).toLocaleString("en-TZ")} TZS`;
}

type WizardItem = {
  order_item_id: string;
  product_name: string;
  variant_name: string | null;
  remaining_quantity: number;
  unit_price: string;
  quantity: number;
  inventory_disposition: string;
  exchange_variant_id: string;
  product_variant_id: string | null;
  product_id: string | null;
};

export function PosReturnsPanel() {
  const [q, setQ] = useState("");
  const [results, setResults] = useState<PosReturnSearchRow[]>([]);
  const [reasons, setReasons] = useState<PosReturnReason[]>([]);
  const [history, setHistory] = useState<PosReturnRecord[]>([]);
  const [selected, setSelected] = useState<PosReturnSearchRow | null>(null);
  const [items, setItems] = useState<WizardItem[]>([]);
  const [returnType, setReturnType] = useState<"refund" | "exchange">("refund");
  const [reasonId, setReasonId] = useState("");
  const [refundMethod, setRefundMethod] = useState("CASH");
  const [notes, setNotes] = useState("");
  const [completed, setCompleted] = useState<PosReturnRecord | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const loadMeta = useCallback(async () => {
    const [reasonRows, hist] = await Promise.all([fetchPosReturnReasons(), fetchPosReturns()]);
    setReasons(reasonRows);
    setHistory(hist);
    if (reasonRows[0] && !reasonId) setReasonId(reasonRows[0].id);
  }, [reasonId]);

  useEffect(() => {
    loadMeta().catch((e: Error) => setError(e.message));
  }, [loadMeta]);

  const search = async () => {
    setBusy(true);
    setError(null);
    try {
      setResults(await searchPosReturns({ q: q || undefined }));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Search failed.");
    } finally {
      setBusy(false);
    }
  };

  const pickOrder = async (row: PosReturnSearchRow) => {
    setBusy(true);
    setError(null);
    setCompleted(null);
    try {
      const detail = await lookupPosReturnOrder(row.order.id);
      setSelected(detail);
      setItems(
        detail.returnable_items.map((item) => ({
          order_item_id: item.order_item_id,
          product_name: item.product_name ?? "Item",
          variant_name: item.variant_name,
          remaining_quantity: item.remaining_quantity,
          unit_price: item.unit_price,
          quantity: 0,
          inventory_disposition: "sellable",
          exchange_variant_id: "",
          product_variant_id: item.product_variant_id,
          product_id: item.product_id ?? null,
        })),
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load order.");
    } finally {
      setBusy(false);
    }
  };

  const selectedLines = useMemo(() => items.filter((i) => i.quantity > 0), [items]);
  const estimatedRefund = useMemo(
    () =>
      selectedLines.reduce((sum, line) => sum + Number(line.unit_price) * line.quantity, 0).toFixed(2),
    [selectedLines],
  );

  const submit = async () => {
    if (!selected?.order) return;
    if (selectedLines.length === 0) {
      setError("Select at least one item quantity.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const result = await processPosReturn({
        order_id: selected.order.id,
        return_type: returnType,
        return_reason_id: reasonId || undefined,
        notes: notes || undefined,
        refund_method: returnType === "refund" ? refundMethod : undefined,
        items: selectedLines.map((line) => ({
          order_item_id: line.order_item_id,
          quantity: line.quantity,
          inventory_disposition: line.inventory_disposition,
          exchange_variant_id:
            returnType === "exchange" && line.exchange_variant_id
              ? line.exchange_variant_id
              : undefined,
        })),
      });
      setCompleted(result.return);
      setSelected(null);
      setItems([]);
      await loadMeta();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Return failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3 border-b border-zinc-200 pb-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900">POS Returns</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Lookup receipt → return / exchange / refund · Order Engine remains source of truth
          </p>
        </div>
        <Link href="/admin/pos" className="rounded-md border border-zinc-300 px-3 py-1.5 text-sm">
          Back to POS
        </Link>
      </div>

      {error ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>
      ) : null}

      {completed ? (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
          <p className="font-semibold">Return completed: {completed.return_number}</p>
          <p>Type: {completed.return_type}</p>
          <p>Refund: {money(completed.refund_total)} via {completed.refund_method ?? "—"}</p>
          <p className="mt-2 text-xs">
            Original receipt: {completed.receipt_snapshot?.original_receipt_number as string}
          </p>
        </div>
      ) : null}

      <div className="flex flex-wrap items-end gap-3 rounded-lg border border-zinc-200 bg-white p-4">
        <label className="flex min-w-[260px] flex-1 flex-col gap-1 text-sm">
          <span className="font-medium text-zinc-700">Search receipt / order / customer</span>
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            className="rounded-md border border-zinc-300 px-3 py-2"
            placeholder="ZION-2026-000001"
          />
        </label>
        <button
          type="button"
          disabled={busy}
          onClick={search}
          className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          Search
        </button>
      </div>

      {!selected ? (
        <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
              <tr>
                <th className="px-3 py-2">Receipt</th>
                <th className="px-3 py-2">Order</th>
                <th className="px-3 py-2">Customer</th>
                <th className="px-3 py-2">Total</th>
                <th className="px-3 py-2">Status</th>
              </tr>
            </thead>
            <tbody>
              {results.map((row) => (
                <tr
                  key={row.order.id}
                  className="cursor-pointer border-t border-zinc-100 hover:bg-zinc-50"
                  onClick={() => {
                    if (row.eligible) pickOrder(row);
                    else setError(row.reason || "Order not eligible.");
                  }}
                >
                  <td className="px-3 py-2">{row.receipt.receipt_number}</td>
                  <td className="px-3 py-2">{row.order.order_number}</td>
                  <td className="px-3 py-2">{row.order.customer_name}</td>
                  <td className="px-3 py-2">{money(row.order.total)}</td>
                  <td className="px-3 py-2">{row.eligible ? "Returnable" : row.reason}</td>
                </tr>
              ))}
              {results.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-3 py-8 text-center text-zinc-500">
                    Search for a completed POS sale to begin a return.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="space-y-4 rounded-lg border border-zinc-200 bg-white p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <p className="font-semibold text-zinc-900">{selected.order.order_number}</p>
              <p className="text-sm text-zinc-500">
                {selected.receipt?.receipt_number} · {selected.order.customer_name}
              </p>
            </div>
            <button type="button" className="text-sm text-zinc-600" onClick={() => setSelected(null)}>
              Change order
            </button>
          </div>

          <div className="flex flex-wrap gap-3">
            <label className="text-sm">
              Type
              <select
                className="ml-2 rounded-md border border-zinc-300 px-2 py-1.5"
                value={returnType}
                onChange={(e) => setReturnType(e.target.value as "refund" | "exchange")}
              >
                <option value="refund">Refund</option>
                <option value="exchange">Exchange</option>
              </select>
            </label>
            <label className="text-sm">
              Reason
              <select
                className="ml-2 rounded-md border border-zinc-300 px-2 py-1.5"
                value={reasonId}
                onChange={(e) => setReasonId(e.target.value)}
              >
                {reasons.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            </label>
            {returnType === "refund" ? (
              <label className="text-sm">
                Refund method
                <select
                  className="ml-2 rounded-md border border-zinc-300 px-2 py-1.5"
                  value={refundMethod}
                  onChange={(e) => setRefundMethod(e.target.value)}
                >
                  <option value="CASH">Cash</option>
                  <option value="MPESA_LIPA">M-Pesa</option>
                  <option value="NMB_BANK">Bank</option>
                </select>
              </label>
            ) : null}
          </div>

          <div className="space-y-2">
            {items.map((line, idx) => (
              <div key={line.order_item_id} className="rounded-md border border-zinc-100 px-3 py-2 text-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="font-medium">{line.product_name}</p>
                    <p className="text-xs text-zinc-500">
                      {line.variant_name} · {money(line.unit_price)} · remaining {line.remaining_quantity}
                    </p>
                  </div>
                  <input
                    type="number"
                    min={0}
                    max={line.remaining_quantity}
                    value={line.quantity}
                    onChange={(e) => {
                      const quantity = Math.max(0, Math.min(line.remaining_quantity, Number(e.target.value || 0)));
                      setItems((prev) => prev.map((p, i) => (i === idx ? { ...p, quantity } : p)));
                    }}
                    className="w-20 rounded-md border border-zinc-300 px-2 py-1"
                  />
                </div>
                {line.quantity > 0 ? (
                  <div className="mt-2 flex flex-wrap gap-2">
                    <select
                      value={line.inventory_disposition}
                      onChange={(e) =>
                        setItems((prev) =>
                          prev.map((p, i) =>
                            i === idx ? { ...p, inventory_disposition: e.target.value } : p,
                          ),
                        )
                      }
                      className="rounded-md border border-zinc-300 px-2 py-1 text-xs"
                    >
                      <option value="sellable">Sellable restock</option>
                      <option value="damaged">Damaged (no restock)</option>
                      <option value="inspection">Inspection required</option>
                    </select>
                    {returnType === "exchange" ? (
                      <input
                        value={line.exchange_variant_id}
                        onChange={(e) =>
                          setItems((prev) =>
                            prev.map((p, i) =>
                              i === idx ? { ...p, exchange_variant_id: e.target.value } : p,
                            ),
                          )
                        }
                        placeholder="Exchange variant UUID"
                        className="min-w-[220px] flex-1 rounded-md border border-zinc-300 px-2 py-1 text-xs"
                      />
                    ) : null}
                  </div>
                ) : null}
              </div>
            ))}
          </div>

          <label className="block text-sm">
            Notes
            <input
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2"
            />
          </label>

          <div className="flex items-center justify-between border-t border-zinc-100 pt-3">
            <p className="text-sm font-medium">Est. refund: {money(estimatedRefund)}</p>
            <button
              type="button"
              disabled={busy || selectedLines.length === 0}
              onClick={submit}
              className="rounded-md bg-[#1f4b3a] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              Complete return
            </button>
          </div>
        </div>
      )}

      <div className="rounded-lg border border-zinc-200 bg-white p-4">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-zinc-500">Recent returns</h2>
        <div className="mt-3 overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="text-xs uppercase text-zinc-500">
              <tr>
                <th className="py-1 pr-3">Return</th>
                <th className="py-1 pr-3">Order</th>
                <th className="py-1 pr-3">Type</th>
                <th className="py-1 pr-3">Refund</th>
                <th className="py-1">Cashier</th>
              </tr>
            </thead>
            <tbody>
              {history.map((row) => (
                <tr key={row.id} className="border-t border-zinc-100">
                  <td className="py-2 pr-3 font-medium">{row.return_number}</td>
                  <td className="py-2 pr-3">{row.order?.order_number}</td>
                  <td className="py-2 pr-3">{row.return_type}</td>
                  <td className="py-2 pr-3">{money(row.refund_total)}</td>
                  <td className="py-2">{row.cashier?.name}</td>
                </tr>
              ))}
              {history.length === 0 ? (
                <tr>
                  <td colSpan={5} className="py-6 text-center text-zinc-500">
                    No POS returns yet.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
