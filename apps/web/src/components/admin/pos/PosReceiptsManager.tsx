"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  fetchPosReceipts,
  fetchPosStores,
  posReceiptPdfUrl,
  posReceiptPreviewUrl,
  printPosReceipt,
  reprintPosReceipt,
  type PosReceipt,
  type PosStore,
} from "@/lib/api/admin-pos";

function openPrintWindow(html: string) {
  const win = window.open("", "_blank", "noopener,noreferrer,width=420,height=720");
  if (!win) return;
  win.document.write(html);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 250);
}

export function PosReceiptsManager() {
  const [stores, setStores] = useState<PosStore[]>([]);
  const [rows, setRows] = useState<PosReceipt[]>([]);
  const [q, setQ] = useState("");
  const [storeId, setStoreId] = useState("");
  const [selected, setSelected] = useState<PosReceipt | null>(null);
  const [previewLayout, setPreviewLayout] = useState("thermal_80");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setBusy(true);
    setError(null);
    try {
      const [storeList, receipts] = await Promise.all([
        fetchPosStores(),
        fetchPosReceipts({
          q: q || undefined,
          store_id: storeId || undefined,
        }),
      ]);
      setStores(storeList);
      setRows(receipts);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load receipts.");
    } finally {
      setBusy(false);
    }
  }, [q, storeId]);

  useEffect(() => {
    load().catch(() => undefined);
  }, [load]);

  const handlePrint = async (receipt: PosReceipt, reprint = false) => {
    setBusy(true);
    setError(null);
    try {
      const result = reprint
        ? await reprintPosReceipt(receipt.id, previewLayout)
        : await printPosReceipt(receipt.id, previewLayout);
      openPrintWindow(result.data.html);
      setSelected(result.data.receipt);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Print failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3 border-b border-zinc-200 pb-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900">POS Receipts</h1>
          <p className="mt-1 text-sm text-zinc-500">Search, preview, reprint — Order Engine remains source of truth</p>
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
          <span className="font-medium text-zinc-700">Search</span>
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Receipt / order / customer"
            className="min-w-[220px] rounded-md border border-zinc-300 px-3 py-2"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-zinc-700">Store</span>
          <select
            className="min-w-[180px] rounded-md border border-zinc-300 px-3 py-2"
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
        <button
          type="button"
          disabled={busy}
          onClick={() => load()}
          className="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          Search
        </button>
      </div>

      <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
              <tr>
                <th className="px-3 py-2 font-medium">Receipt</th>
                <th className="px-3 py-2 font-medium">Order</th>
                <th className="px-3 py-2 font-medium">Store</th>
                <th className="px-3 py-2 font-medium">Customer</th>
                <th className="px-3 py-2 font-medium">Prints</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => {
                const customer =
                  (row.snapshot as { customer?: { name?: string } } | undefined)?.customer?.name ??
                  row.order?.customer_name ??
                  "—";
                return (
                  <tr
                    key={row.id}
                    className={`cursor-pointer border-b border-zinc-100 hover:bg-zinc-50 ${
                      selected?.id === row.id ? "bg-emerald-50/60" : ""
                    }`}
                    onClick={() => setSelected(row)}
                  >
                    <td className="px-3 py-2 font-medium">{row.receipt_number}</td>
                    <td className="px-3 py-2">{row.order?.order_number ?? "—"}</td>
                    <td className="px-3 py-2">{row.store?.name ?? "—"}</td>
                    <td className="px-3 py-2">{customer}</td>
                    <td className="px-3 py-2">{row.print_count ?? 0}</td>
                  </tr>
                );
              })}
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-3 py-8 text-center text-zinc-500">
                    {busy ? "Loading…" : "No receipts found."}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <div className="space-y-3 rounded-lg border border-zinc-200 bg-white p-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-zinc-500">Receipt detail</h2>
          {!selected ? (
            <p className="text-sm text-zinc-500">Select a receipt to preview.</p>
          ) : (
            <>
              <div className="text-sm text-zinc-700">
                <p className="font-semibold text-zinc-900">{selected.receipt_number}</p>
                <p>Order: {selected.order?.order_number}</p>
                <p>Store: {selected.store?.name}</p>
              </div>
              <label className="flex flex-col gap-1 text-sm">
                <span className="font-medium text-zinc-700">Layout</span>
                <select
                  value={previewLayout}
                  onChange={(e) => setPreviewLayout(e.target.value)}
                  className="rounded-md border border-zinc-300 px-3 py-2"
                >
                  <option value="thermal_80">80mm Thermal</option>
                  <option value="a4">A4</option>
                </select>
              </label>
              <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => handlePrint(selected, false)}
                  className="rounded-md bg-zinc-900 px-3 py-2 text-sm text-white disabled:opacity-50"
                >
                  Print
                </button>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => handlePrint(selected, true)}
                  className="rounded-md border border-zinc-300 px-3 py-2 text-sm disabled:opacity-50"
                >
                  Reprint
                </button>
                <a
                  href={posReceiptPdfUrl(selected.id)}
                  className="rounded-md border border-zinc-300 px-3 py-2 text-sm"
                  target="_blank"
                  rel="noreferrer"
                >
                  Download PDF
                </a>
              </div>
              <iframe
                title="Receipt preview"
                src={posReceiptPreviewUrl(selected.id, previewLayout)}
                className="h-[520px] w-full rounded-md border border-zinc-200 bg-zinc-50"
              />
            </>
          )}
        </div>
      </div>
    </div>
  );
}
