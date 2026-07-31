"use client";



import Link from "next/link";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import {

  ADMIN_REPORT_TYPES,

  ADMIN_REPORT_TYPE_LABELS,

  AdminReportingApiError,

  downloadAdminReport,

  fetchAdminReportingDashboard,

  type AdminReportingDashboard as AdminReportingDashboardData,

  type AdminReportType,

} from "@/lib/api/admin-reporting";

import {

  computeChannelSummary,

  computeFulfilmentPipeline,

  computeTodayOverview,

} from "@/lib/admin/dashboard-command-center";

import { AdminDashboardContent } from "@/components/admin/AdminDashboardContent";

import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";

import { AdminRefreshStatusBar } from "@/components/admin/AdminRefreshStatusBar";

import { formatPrice } from "@/lib/catalog/utils";

import { useAdminAutoRefresh } from "@/hooks/use-admin-auto-refresh";

import { useAdminDashboardPreferences } from "@/hooks/use-admin-dashboard-preferences";



function defaultFrom(): string {

  const d = new Date();

  d.setDate(d.getDate() - 29);

  return d.toISOString().slice(0, 10);

}



function defaultTo(): string {

  return new Date().toISOString().slice(0, 10);

}



function formatShortDate(iso: string): string {

  try {

    return new Intl.DateTimeFormat("en-TZ", {

      month: "short",

      day: "numeric",

    }).format(new Date(`${iso}T00:00:00`));

  } catch {

    return iso.slice(5);

  }

}



function formatWhen(value?: string | null): string {

  if (!value) return "—";

  try {

    return new Intl.DateTimeFormat("en-TZ", {

      dateStyle: "medium",

      timeStyle: "short",

    }).format(new Date(value));

  } catch {

    return value;

  }

}



function maxValue(values: number[]): number {

  return Math.max(...values, 1);

}



function DailyBarChart({

  data,

  valueKey,

  formatValue,

}: {

  data: Array<{ date: string; value: number }>;

  valueKey: string;

  formatValue: (n: number) => string;

}) {

  if (data.length === 0) {

    return <p className="mt-8 text-center text-sm text-zinc-500">No data for this range.</p>;

  }



  const peak = maxValue(data.map((p) => p.value));

  const step = Math.max(1, Math.ceil(data.length / 8));



  return (

    <div className="mt-6 flex h-48 items-end gap-1 sm:gap-1.5">

      {data.map((point, index) => {

        const height = `${Math.max(4, (point.value / peak) * 100)}%`;

        const showLabel = index === 0 || index === data.length - 1 || index % step === 0;

        return (

          <div key={`${valueKey}-${point.date}`} className="flex min-w-0 flex-1 flex-col items-center gap-1.5">

            <div

              title={`${formatShortDate(point.date)}: ${formatValue(point.value)}`}

              className="w-full max-w-7 rounded-t-md bg-gradient-to-t from-[#8b6914] to-[#e8c547] shadow-[0_4px_16px_rgba(201,162,39,0.2)]"

              style={{ height }}

            />

            <span className="h-3 truncate text-[9px] font-medium text-zinc-500">

              {showLabel ? formatShortDate(point.date) : ""}

            </span>

          </div>

        );

      })}

    </div>

  );

}



type AdminReportingDashboardProps = {

  title?: string;

  eyebrow?: string;

  description?: string;

};



export function AdminReportingDashboard({

  title = "Command center",

  eyebrow = "Operations dashboard",

  description = "Prioritize attention items first, then review operations, growth, and detailed analytics.",

}: AdminReportingDashboardProps = {}) {

  const { orders } = useAdminOrders();

  const { visibleSectionOrder, preferences, toggleCollapsed } = useAdminDashboardPreferences();

  const [from, setFrom] = useState(defaultFrom);

  const [to, setTo] = useState(defaultTo);

  const [appliedFrom, setAppliedFrom] = useState(defaultFrom);

  const [appliedTo, setAppliedTo] = useState(defaultTo);

  const [data, setData] = useState<AdminReportingDashboardData | null>(null);

  const [loading, setLoading] = useState(true);

  const [error, setError] = useState<string | null>(null);

  const [exporting, setExporting] = useState<string | null>(null);

  const markSyncedRef = useRef<() => void>(() => {});



  const load = useCallback(

    async (rangeFrom: string, rangeTo: string, options?: { background?: boolean }) => {

      if (!options?.background) {

        setLoading(true);

      }

      setError(null);

      try {

        const next = await fetchAdminReportingDashboard({

          from: rangeFrom,

          to: rangeTo,

        });

        setData(next);

        setAppliedFrom(next.period.from);

        setAppliedTo(next.period.to);

        setFrom(next.period.from);

        setTo(next.period.to);

        markSyncedRef.current();

      } catch (err) {

        if (!options?.background) {

          setData(null);

        }

        setError(

          err instanceof AdminReportingApiError

            ? err.message

            : "Unable to load reporting dashboard.",

        );

      } finally {

        if (!options?.background) {

          setLoading(false);

        }

      }

    },

    [],

  );



  const refreshDashboard = useCallback(

    async (options?: { background?: boolean }) => {

      await load(appliedFrom, appliedTo, options);

    },

    [appliedFrom, appliedTo, load],

  );



  const autoRefresh = useAdminAutoRefresh({

    page: "command_center",

    enabled: Boolean(data),

    onRefresh: (options) => refreshDashboard(options),

  });

  markSyncedRef.current = autoRefresh.markSynced;



  useEffect(() => {

    void load(appliedFrom, appliedTo);

    // Initial load only — range applied via Apply button / presets.

    // eslint-disable-next-line react-hooks/exhaustive-deps

  }, []);



  const applyRange = () => {

    void load(from, to);

  };



  const applyPreset = (days: number) => {

    const end = defaultTo();

    const startDate = new Date();

    startDate.setDate(startDate.getDate() - (days - 1));

    const start = startDate.toISOString().slice(0, 10);

    setFrom(start);

    setTo(end);

    void load(start, end);

  };



  const revenueSeries = useMemo(

    () =>

      (data?.charts?.daily_revenue ?? []).map((p) => ({

        date: p.date,

        value: Number(p.revenue) || 0,

      })),

    [data],

  );



  const ordersSeries = useMemo(

    () =>

      (data?.charts?.orders_trend ?? []).map((p) => ({

        date: p.date,

        value: Number(p.count) || 0,

      })),

    [data],

  );



  const commandCenter = useMemo(() => {

    if (!data) return null;



    const hasApiSections =

      data.overview &&

      data.operations &&

      data.china_pipeline &&

      data.tz_local &&

      data.attention_items;



    if (hasApiSections) {

      return { source: "api" as const, data };

    }



    return {

      source: "client" as const,

      today: computeTodayOverview(orders, data),

      china: computeChannelSummary(orders, "china"),

      local: computeChannelSummary(orders, "local"),

      pipeline: computeFulfilmentPipeline(orders, data),

    };

  }, [data, orders]);



  const handleExport = async (type: AdminReportType, format: "csv" | "xlsx") => {

    setExporting(`${type}-${format}`);

    setError(null);

    try {

      await downloadAdminReport(type, format, {

        from: appliedFrom,

        to: appliedTo,

      });

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

            {eyebrow}

          </p>

          <h1 className="mt-1 text-2xl font-bold text-zinc-900 sm:text-3xl">{title}</h1>

          <p className="mt-1 text-sm text-zinc-500">{description}</p>

        </div>



        <div className="flex flex-wrap items-end gap-3">

          <AdminRefreshStatusBar

            lastUpdatedAt={autoRefresh.lastUpdatedAt}

            isRefreshing={autoRefresh.isRefreshing || loading}

            policyLabel={autoRefresh.policyLabel}

            onRefresh={() => void autoRefresh.refreshNow({ manual: true })}

            className="w-full sm:w-auto"

          />

          <div className="inline-flex rounded-xl border border-zinc-200 bg-white p-1 shadow-sm">

            {[7, 14, 30].map((days) => (

              <button

                key={days}

                type="button"

                onClick={() => applyPreset(days)}

                className="admin-touch-target rounded-lg px-3 py-2 text-xs font-bold text-zinc-500 transition hover:text-zinc-900"

              >

                {days}d

              </button>

            ))}

          </div>

          <label className="block min-w-[9rem] flex-1 text-xs font-semibold text-zinc-500 sm:flex-none">

            From

            <input

              type="date"

              value={from}

              onChange={(e) => setFrom(e.target.value)}

              className="admin-touch-input mt-1 block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"

            />

          </label>

          <label className="block min-w-[9rem] flex-1 text-xs font-semibold text-zinc-500 sm:flex-none">

            To

            <input

              type="date"

              value={to}

              onChange={(e) => setTo(e.target.value)}

              className="admin-touch-input mt-1 block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm"

            />

          </label>

          <button type="button" onClick={applyRange} className="admin-btn-primary w-full sm:w-auto">

            Apply

          </button>

          <Link href="/admin/alerts" className="admin-btn-secondary w-full sm:w-auto">

            Alerts

          </Link>

          <Link href="/admin/reports" className="admin-btn-secondary w-full sm:w-auto">

            All reports

          </Link>

        </div>

      </div>



      {error ? (

        <div className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">

          {error}

        </div>

      ) : null}



      {loading && !data ? (

        <div className="mt-8 space-y-4">

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

            {Array.from({ length: 8 }).map((_, i) => (

              <div key={i} className="h-24 animate-pulse rounded-xl bg-zinc-100 sm:h-28" />

            ))}

          </div>

          <div className="h-64 animate-pulse rounded-xl bg-zinc-100" />

        </div>

      ) : data && commandCenter ? (

        <div className={`mt-8 ${loading ? "opacity-60" : ""}`}>

          <AdminDashboardContent

            data={data}

            commandCenter={commandCenter}

            sectionOrder={visibleSectionOrder}

            collapsedSections={preferences.collapsedSections}

            onToggleCollapsed={toggleCollapsed}

            appliedFrom={appliedFrom}

            appliedTo={appliedTo}

            revenueSeries={revenueSeries}

            ordersSeries={ordersSeries}

            formatWhen={formatWhen}

            DailyBarChart={DailyBarChart}

            adminReportTypes={ADMIN_REPORT_TYPES}

            adminReportTypeLabels={ADMIN_REPORT_TYPE_LABELS}

            exporting={exporting}

            onExport={(type, format) => void handleExport(type, format)}

          />

        </div>

      ) : null}

    </div>

  );

}

