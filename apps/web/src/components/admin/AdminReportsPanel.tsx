"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ADMIN_REPORT_TYPES,
  ADMIN_REPORT_TYPE_LABELS,
  AdminReportingApiError,
  downloadAdminReport,
  fetchAdminReport,
  type AdminReportPayload,
  type AdminReportType,
} from "@/lib/api/admin-reporting";
import { formatPrice } from "@/lib/catalog/utils";

function defaultFrom(): string {
  const d = new Date();
  d.setDate(d.getDate() - 29);
  return d.toISOString().slice(0, 10);
}

function defaultTo(): string {
  return new Date().toISOString().slice(0, 10);
}

function formatCell(value: unknown): string {
  if (value == null) return "—";
  if (typeof value === "number") {
    return Number.isFinite(value) ? value.toLocaleString("en-TZ") : String(value);
  }
  if (typeof value === "boolean") return value ? "Yes" : "No";
  return String(value);
}

function formatSummaryValue(key: string, value: unknown): string {
  if (typeof value === "number") {
    if (
      key.includes("revenue") ||
      key.includes("amount") ||
      key.includes("total_amount") ||
      key === "total"
    ) {
      return formatPrice(value);
    }
    return value.toLocaleString("en-TZ");
  }
  return formatCell(value);
}

function humanizeKey(key: string): string {
  return key.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

export function AdminReportsPanel() {
  const [type, setType] = useState<AdminReportType>("orders");
  const [from, setFrom] = useState(defaultFrom);
  const [to, setTo] = useState(defaultTo);
  const [report, setReport] = useState<AdminReportPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exporting, setExporting] = useState<"csv" | "xlsx" | null>(null);

  const load = useCallback(
    async (range?: { from: string; to: string; type?: AdminReportType }) => {
      const nextType = range?.type ?? type;
      const nextFrom = range?.from ?? from;
      const nextTo = range?.to ?? to;
      setLoading(true);
      setError(null);
      try {
        const next = await fetchAdminReport(nextType, {
          from: nextFrom,
          to: nextTo,
        });
        setReport(next);
        if (next.period?.from) setFrom(next.period.from);
        if (next.period?.to) setTo(next.period.to);
      } catch (err) {
        setReport(null);
        setError(
          err instanceof AdminReportingApiError
            ? err.message
            : "Unable to load report.",
        );
      } finally {
        setLoading(false);
      }
    },
    [type, from, to],
  );

  useEffect(() => {
    void load({ type, from, to });
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load on type change only
  }, [type]);

  const summaryEntries = useMemo(() => {
    if (!report?.summary) return [];
    return Object.entries(report.summary);
  }, [report]);

  const handleExport = async (format: "csv" | "xlsx") => {
    setExporting(format);
    setError(null);
    try {
      await downloadAdminReport(type, format, { from, to });
    } catch (err) {
      setError(
        err instanceof AdminReportingApiError
          ? err.message
          : "Unable to export report.",
      );
    } finally {
      setExporting(null);
    }
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="admin-page-header">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Reporting engine
          </p>
          <h1 className="mt-1 text-2xl font-bold text-zinc-900 sm:text-3xl">Reports</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Filter by date range, review summaries, and export CSV or XLSX.
          </p>
        </div>
      </div>

      <div className="mt-6 flex flex-wrap items-end gap-3">
        <label className="block text-xs font-semibold text-zinc-500">
          Report type
          <select
            value={type}
            onChange={(e) => setType(e.target.value as AdminReportType)}
            className="mt-1 block min-w-[180px] rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"
          >
            {ADMIN_REPORT_TYPES.map((t) => (
              <option key={t} value={t}>
                {ADMIN_REPORT_TYPE_LABELS[t]}
              </option>
            ))}
          </select>
        </label>
        <label className="block text-xs font-semibold text-zinc-500">
          From
          <input
            type="date"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
            className="mt-1 block rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"
          />
        </label>
        <label className="block text-xs font-semibold text-zinc-500">
          To
          <input
            type="date"
            value={to}
            onChange={(e) => setTo(e.target.value)}
            className="mt-1 block rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"
          />
        </label>
        <button
          type="button"
          onClick={() => void load({ type, from, to })}
          className="admin-btn-primary"
        >
          Apply
        </button>
        <button
          type="button"
          disabled={exporting !== null || loading}
          onClick={() => void handleExport("csv")}
          className="admin-btn-secondary disabled:opacity-50"
        >
          {exporting === "csv" ? "Exporting…" : "Export CSV"}
        </button>
        <button
          type="button"
          disabled={exporting !== null || loading}
          onClick={() => void handleExport("xlsx")}
          className="admin-btn-secondary disabled:opacity-50"
        >
          {exporting === "xlsx" ? "Exporting…" : "Export XLSX"}
        </button>
      </div>

      {error ? (
        <div className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      ) : null}

      {loading && !report ? (
        <div className="mt-8 space-y-4">
          <div className="grid gap-4 sm:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="h-24 animate-pulse rounded-xl bg-zinc-100" />
            ))}
          </div>
          <div className="h-72 animate-pulse rounded-xl bg-zinc-100" />
        </div>
      ) : report ? (
        <div className={`mt-8 space-y-6 ${loading ? "opacity-60" : ""}`}>
          <section>
            <h2 className="text-sm font-bold text-zinc-900">
              {ADMIN_REPORT_TYPE_LABELS[type]} summary
            </h2>
            <p className="mt-1 text-xs text-zinc-500">
              {report.period.from} → {report.period.to}
            </p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {summaryEntries.map(([key, value]) => (
                <div
                  key={key}
                  className="rounded-xl border border-zinc-200 bg-white px-4 py-3 shadow-sm"
                >
                  <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                    {humanizeKey(key)}
                  </p>
                  <p className="mt-1 text-lg font-bold text-zinc-900">
                    {formatSummaryValue(key, value)}
                  </p>
                </div>
              ))}
            </div>
          </section>

          <section className="admin-card overflow-hidden">
            <div className="border-b border-zinc-200 px-5 py-4">
              <h2 className="text-sm font-bold text-zinc-900">
                Rows ({report.rows.length})
              </h2>
            </div>
            {report.rows.length === 0 ? (
              <p className="px-5 py-12 text-center text-sm text-zinc-500">
                No rows for this range.
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[640px] text-left text-sm">
                  <thead>
                    <tr className="border-b border-zinc-100 bg-zinc-50/80">
                      {report.columns.map((col) => (
                        <th
                          key={col}
                          className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500"
                        >
                          {humanizeKey(col)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-100">
                    {report.rows.map((row, idx) => (
                      <tr key={idx} className="hover:bg-zinc-50/80">
                        {report.columns.map((col) => (
                          <td key={col} className="px-4 py-3 text-zinc-800">
                            {formatCell(row[col])}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </div>
      ) : null}
    </div>
  );
}
