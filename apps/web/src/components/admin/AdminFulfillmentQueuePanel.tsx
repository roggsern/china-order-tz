"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { AdminFulfillmentBulkActionBar } from "@/components/admin/AdminFulfillmentBulkActionBar";
import { AdminRefreshStatusBar } from "@/components/admin/AdminRefreshStatusBar";
import { mapAdminFulfillmentToBulkSelectionContext } from "@/lib/admin/fulfillment-bulk";
import {
  AdminFulfillmentApiError,
  fetchAdminFulfillmentsPage,
  type AdminFulfillment,
} from "@/lib/api/admin-fulfillments";
import {
  computeQueueSummaryCards,
  filterQueueRows,
  FULFILLMENT_STATUS_STYLES,
  mapAdminFulfillmentToQueueRow,
  resolveQueueRowVisualIndicator,
  type FulfillmentActionRequiredFilter,
  type FulfillmentJourneyFilter,
  type FulfillmentQueueRow,
  type QueueRowVisualIndicator,
  type QueueSummaryKey,
} from "@/lib/admin/fulfillment-operational";
import {
  buildFulfillmentQueueServerFilters,
  clampPage,
  FULFILLMENT_QUEUE_PAGE_SIZE,
} from "@/lib/admin/fulfillment-queue-pagination";
import { aggregateQueueSummaryCards } from "@/lib/admin/fulfillment-queue-summary";
import {
  clearTableSelection,
  resolveTableSelectionState,
  toggleSelectAllVisible,
  toggleTableSelection,
} from "@/lib/admin/table-selection";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import { useAdminAutoRefresh } from "@/hooks/use-admin-auto-refresh";
import { preserveSelectionAfterRefresh } from "@/lib/admin/admin-auto-refresh";

const STATUS_STYLES = FULFILLMENT_STATUS_STYLES;

const JOURNEY_FILTERS: { value: FulfillmentJourneyFilter; label: string }[] = [
  { value: "all", label: "All journeys" },
  { value: "china", label: "Order from China" },
  { value: "local", label: "Buy From TZ" },
];

const STATUS_FILTERS = [
  { value: "all", label: "All statuses" },
  { value: "pending", label: "Pending" },
  { value: "processing", label: "Processing" },
  { value: "ready_for_shipping", label: "Ready to ship" },
  { value: "shipped", label: "Shipped" },
  { value: "delivered", label: "Delivered" },
];

const ACTION_FILTERS: { value: FulfillmentActionRequiredFilter; label: string }[] = [
  { value: "all", label: "All actions" },
  { value: "needs_purchase", label: "Needs purchase" },
  { value: "needs_warehouse", label: "Needs warehouse action" },
  { value: "needs_shipment", label: "Needs shipment" },
  { value: "completed", label: "Completed" },
];

const INDICATOR_STYLES: Record<
  QueueRowVisualIndicator,
  { dot: string; label: string; row: string }
> = {
  normal: {
    dot: "bg-zinc-300",
    label: "",
    row: "",
  },
  urgent: {
    dot: "bg-amber-500",
    label: "Urgent",
    row: "border-l-2 border-l-amber-400",
  },
  delayed: {
    dot: "bg-red-500",
    label: "Delayed",
    row: "border-l-2 border-l-red-400",
  },
  completed: {
    dot: "bg-emerald-500",
    label: "Complete",
    row: "opacity-80",
  },
};

function QueueIndicatorBadge({ indicator }: { indicator: QueueRowVisualIndicator }) {
  if (indicator === "normal") {
    return null;
  }

  const styles = INDICATOR_STYLES[indicator];
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ${
        indicator === "completed"
          ? "bg-emerald-50 text-emerald-800 ring-emerald-200"
          : indicator === "delayed"
            ? "bg-red-50 text-red-800 ring-red-200"
            : "bg-amber-50 text-amber-900 ring-amber-200"
      }`}
    >
      <span className={`h-1.5 w-1.5 rounded-full ${styles.dot}`} />
      {styles.label}
    </span>
  );
}

function QueueProductCell({ row }: { row: FulfillmentQueueRow }) {
  return (
    <div className="flex min-w-0 items-start gap-2.5">
      <div className="h-10 w-10 shrink-0 overflow-hidden rounded-lg ring-1 ring-zinc-200/80">
        <ProductImageDisplay
          src={row.productImageUrl ?? undefined}
          className="h-full w-full"
          emojiClassName="text-lg"
        />
      </div>
      <div className="min-w-0">
        <p className="truncate text-sm font-medium text-zinc-900">{row.productName}</p>
        {row.productVariant ? (
          <p className="truncate text-xs text-zinc-500">{row.productVariant}</p>
        ) : null}
        {row.additionalItemCount > 0 ? (
          <p className="text-[11px] font-medium text-zinc-500">
            +{row.additionalItemCount} more
          </p>
        ) : null}
      </div>
    </div>
  );
}

function QueueMobileCard({
  row,
  selected,
  onToggleSelected,
}: {
  row: FulfillmentQueueRow;
  selected: boolean;
  onToggleSelected: () => void;
}) {
  const indicator = resolveQueueRowVisualIndicator(row);

  return (
    <article
      className={`rounded-xl border border-zinc-100 bg-white p-4 shadow-sm ${INDICATOR_STYLES[indicator].row}`}
    >
      <div className="flex items-start gap-3">
        <input
          type="checkbox"
          checked={selected}
          onChange={onToggleSelected}
          aria-label={`Select fulfilment ${row.orderNumber}`}
          className="mt-1 h-4 w-4 rounded border-zinc-300 text-zinc-900"
        />
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="font-mono text-sm font-bold text-zinc-900">{row.orderNumber}</p>
              <p className="mt-1 truncate text-sm text-zinc-600">{row.productName}</p>
            </div>
            <QueueIndicatorBadge indicator={indicator} />
          </div>

          <div className="mt-3 flex flex-wrap items-center gap-2">
            <span
              className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ${
                STATUS_STYLES[row.status] ?? "bg-zinc-50 text-zinc-700 ring-zinc-200"
              }`}
            >
              {row.currentStage}
            </span>
            <span className="text-[11px] font-semibold text-zinc-500">{row.journeyLabel}</span>
          </div>

          <p className="mt-3 text-sm font-medium text-zinc-800">{row.requiredAction}</p>

          <Link
            href={`/admin/fulfillments/${encodeURIComponent(row.id)}`}
            className="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800"
          >
            Open
          </Link>
        </div>
      </div>
    </article>
  );
}

function SummaryCard({
  label,
  description,
  count,
  active,
  onClick,
}: {
  label: string;
  description: string;
  count: number;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-xl border bg-white p-4 text-left transition hover:border-zinc-300 ${
        active ? "border-zinc-900 ring-1 ring-zinc-900/10" : "border-zinc-100"
      }`}
    >
      <p className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">{label}</p>
      <p className="mt-2 text-2xl font-bold tabular-nums text-zinc-900">{count}</p>
      <p className="mt-1 text-xs leading-relaxed text-zinc-500">{description}</p>
    </button>
  );
}

export function AdminFulfillmentQueuePanel() {
  const { permissions } = useAdminPermissions();
  const [rows, setRows] = useState<AdminFulfillment[]>([]);
  const [loading, setLoading] = useState(true);
  const [summaryLoading, setSummaryLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [journeyFilter, setJourneyFilter] = useState<FulfillmentJourneyFilter>("all");
  const [statusFilter, setStatusFilter] = useState("all");
  const [actionFilter, setActionFilter] = useState<FulfillmentActionRequiredFilter>("all");
  const [searchQuery, setSearchQuery] = useState("");
  const [summaryFocus, setSummaryFocus] = useState<QueueSummaryKey | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [totalRows, setTotalRows] = useState(0);
  const [summaryCards, setSummaryCards] = useState(() => computeQueueSummaryCards([]));
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => clearTableSelection());

  const serverFilters = useMemo(
    () =>
      buildFulfillmentQueueServerFilters({
        journey: journeyFilter,
        status: statusFilter,
      }),
    [journeyFilter, statusFilter],
  );

  const reloadPage = useCallback(async (options?: { background?: boolean }) => {
    if (!options?.background) {
      setLoading(true);
    }
    setError(null);
    try {
      const result = await fetchAdminFulfillmentsPage({
        ...serverFilters,
        page,
        perPage: FULFILLMENT_QUEUE_PAGE_SIZE,
      });
      setRows(result.items);
      setLastPage(result.meta.last_page);
      setTotalRows(result.meta.total);
      setPage(clampPage(page, result.meta.last_page));
      setSelectedIds((current) =>
        preserveSelectionAfterRefresh(
          current,
          result.items.map((row) => row.id),
        ),
      );
    } catch (err) {
      if (!options?.background) {
        setRows([]);
        setLastPage(1);
        setTotalRows(0);
      }
      setError(
        err instanceof AdminFulfillmentApiError
          ? err.message
          : "Unable to load fulfillment queue.",
      );
    } finally {
      if (!options?.background) {
        setLoading(false);
      }
    }
  }, [page, serverFilters]);

  const reloadSummary = useCallback(async (options?: { background?: boolean }) => {
    if (!options?.background) {
      setSummaryLoading(true);
    }
    try {
      const cards = await aggregateQueueSummaryCards(serverFilters);
      setSummaryCards(cards);
    } catch {
      if (!options?.background) {
        setSummaryCards(computeQueueSummaryCards([]));
      }
    } finally {
      if (!options?.background) {
        setSummaryLoading(false);
      }
    }
  }, [serverFilters]);

  const markSyncedRef = useRef<() => void>(() => {});

  const refreshQueue = useCallback(
    async (options?: { background?: boolean }) => {
      await Promise.all([reloadPage(options), reloadSummary(options)]);
      markSyncedRef.current();
    },
    [reloadPage, reloadSummary],
  );

  const autoRefresh = useAdminAutoRefresh({
    page: "fulfillment_queue",
    enabled: !loading,
    onRefresh: (options) => refreshQueue(options),
  });
  markSyncedRef.current = autoRefresh.markSynced;

  useEffect(() => {
    void reloadPage().then(() => {
      markSyncedRef.current();
    });
  }, [reloadPage]);

  useEffect(() => {
    void reloadSummary().then(() => {
      markSyncedRef.current();
    });
  }, [reloadSummary]);

  useEffect(() => {
    setPage(1);
    setSelectedIds(clearTableSelection());
  }, [journeyFilter, statusFilter]);

  const pageQueueRows = useMemo(
    () => rows.map((row) => mapAdminFulfillmentToQueueRow(row)),
    [rows],
  );

  const queueRows = useMemo(() => {
    let filtered = filterQueueRows(pageQueueRows, {
      journey: journeyFilter,
      status: statusFilter,
      actionRequired: actionFilter,
      search: searchQuery,
    });

    if (summaryFocus === "awaiting_purchase") {
      filtered = filtered.filter((row) => row.actionCategory === "needs_purchase");
    } else if (summaryFocus === "warehouse_processing") {
      filtered = filtered.filter(
        (row) => row.actionCategory === "needs_warehouse" || row.status === "processing",
      );
    } else if (summaryFocus === "ready_to_ship") {
      filtered = filtered.filter((row) => row.status === "ready_for_shipping");
    } else if (summaryFocus === "in_transit") {
      filtered = filtered.filter((row) => row.status === "shipped");
    } else if (summaryFocus === "needs_attention") {
      filtered = filtered.filter((row) => {
        const indicator = resolveQueueRowVisualIndicator(row);
        return indicator === "delayed" || indicator === "urgent";
      });
    }

    return filtered.sort((a, b) => b.ageMs - a.ageMs);
  }, [
    pageQueueRows,
    journeyFilter,
    statusFilter,
    actionFilter,
    searchQuery,
    summaryFocus,
  ]);

  const visibleIds = useMemo(() => queueRows.map((row) => row.id), [queueRows]);
  const selection = resolveTableSelectionState(selectedIds, visibleIds);
  const selectedBulkRows = useMemo(
    () =>
      rows
        .filter((row) => selectedIds.has(row.id))
        .map((row) => mapAdminFulfillmentToBulkSelectionContext(row)),
    [rows, selectedIds],
  );

  const handleSummaryClick = (key: QueueSummaryKey) => {
    setSummaryFocus((current) => (current === key ? null : key));
  };

  const handleRefresh = () => {
    void autoRefresh.refreshNow({ manual: true });
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#8b6914]">
            Operations
          </p>
          <h1 className="mt-1 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
            Fulfilment Command Center
          </h1>
          <p className="mt-1 max-w-2xl text-sm text-zinc-500">
            Monitor open fulfilments, spot delays, and open the operational workspace for each
            order.
          </p>
        </div>
        <AdminRefreshStatusBar
          lastUpdatedAt={autoRefresh.lastUpdatedAt}
          isRefreshing={autoRefresh.isRefreshing || loading || summaryLoading}
          policyLabel={autoRefresh.policyLabel}
          onRefresh={handleRefresh}
          className="w-full lg:w-auto"
        />
      </header>

      <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {summaryCards.map((card) => (
          <SummaryCard
            key={card.key}
            label={card.label}
            description={card.description}
            count={summaryLoading ? 0 : card.count}
            active={summaryFocus === card.key}
            onClick={() => handleSummaryClick(card.key)}
          />
        ))}
      </div>

      <div className="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))]">
        <label className="flex flex-col gap-1 text-xs font-semibold text-zinc-500">
          Search
          <input
            type="search"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Order, customer, or product"
            className="min-h-11 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-normal text-zinc-900 placeholder:text-zinc-400"
          />
        </label>
        <label className="flex flex-col gap-1 text-xs font-semibold text-zinc-500">
          Journey
          <select
            value={journeyFilter}
            onChange={(e) => setJourneyFilter(e.target.value as FulfillmentJourneyFilter)}
            className="min-h-11 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-normal text-zinc-900"
          >
            {JOURNEY_FILTERS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-xs font-semibold text-zinc-500">
          Status
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="min-h-11 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-normal text-zinc-900"
          >
            {STATUS_FILTERS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-xs font-semibold text-zinc-500">
          Action required
          <select
            value={actionFilter}
            onChange={(e) => setActionFilter(e.target.value as FulfillmentActionRequiredFilter)}
            className="min-h-11 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-normal text-zinc-900"
          >
            {ACTION_FILTERS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      {summaryFocus ? (
        <p className="mt-3 text-xs font-medium text-zinc-600">
          Showing queue slice:{" "}
          <span className="font-semibold text-zinc-900">
            {summaryCards.find((card) => card.key === summaryFocus)?.label}
          </span>
          .{" "}
          <button
            type="button"
            onClick={() => setSummaryFocus(null)}
            className="font-semibold text-[#8b6914] underline-offset-2 hover:underline"
          >
            Clear slice
          </button>
        </p>
      ) : null}

      {searchQuery.trim() || actionFilter !== "all" ? (
        <p className="mt-3 text-xs text-zinc-500">
          Search and action filters apply to the current page only.
        </p>
      ) : null}

      {error ? (
        <p
          className="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {error}
        </p>
      ) : null}

      <div className="admin-card mt-5 overflow-hidden">
        <AdminFulfillmentBulkActionBar
          selectedCount={selection.selectedCount}
          selectedRows={selectedBulkRows}
          permissions={permissions}
          onClearSelection={() => setSelectedIds(clearTableSelection())}
          onCompleted={() => {
            void reloadPage();
            void reloadSummary();
          }}
        />

        {loading ? (
          <div className="p-8 text-sm text-zinc-500">Loading fulfilments…</div>
        ) : queueRows.length === 0 ? (
          <div className="p-8 text-center text-sm text-zinc-500">
            No fulfilments match these filters on this page.
          </div>
        ) : (
          <>
            <div className="hidden lg:block">
              <table className="w-full table-fixed text-left text-sm">
                <thead className="border-b border-zinc-100 bg-zinc-50/80 text-[11px] uppercase tracking-wide text-zinc-500">
                  <tr>
                    <th className="w-[4%] px-3 py-3 font-semibold">
                      <input
                        type="checkbox"
                        checked={selection.allVisibleSelected}
                        onChange={() =>
                          setSelectedIds((current) =>
                            toggleSelectAllVisible(current, visibleIds),
                          )
                        }
                        aria-label="Select all fulfilments on this page"
                        className="h-4 w-4 rounded border-zinc-300 text-zinc-900"
                      />
                    </th>
                    <th className="w-[10%] px-3 py-3 font-semibold">Order</th>
                    <th className="w-[17%] px-3 py-3 font-semibold">Product</th>
                    <th className="w-[11%] px-3 py-3 font-semibold">Journey</th>
                    <th className="w-[11%] px-3 py-3 font-semibold">Stage</th>
                    <th className="w-[7%] px-3 py-3 font-semibold">Age</th>
                    <th className="w-[15%] px-3 py-3 font-semibold">Required action</th>
                    <th className="w-[11%] px-3 py-3 font-semibold">Assigned</th>
                    <th className="w-[14%] px-3 py-3 font-semibold">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {queueRows.map((row) => {
                    const indicator = resolveQueueRowVisualIndicator(row);
                    return (
                      <tr
                        key={row.id}
                        className={`border-b border-zinc-50 align-top ${INDICATOR_STYLES[indicator].row}`}
                      >
                        <td className="px-3 py-3">
                          <input
                            type="checkbox"
                            checked={selectedIds.has(row.id)}
                            onChange={() =>
                              setSelectedIds((current) => toggleTableSelection(current, row.id))
                            }
                            aria-label={`Select fulfilment ${row.orderNumber}`}
                            className="h-4 w-4 rounded border-zinc-300 text-zinc-900"
                          />
                        </td>
                        <td className="px-3 py-3">
                          <div className="space-y-1">
                            <p className="truncate font-mono text-sm font-semibold text-zinc-900">
                              {row.orderNumber}
                            </p>
                            <QueueIndicatorBadge indicator={indicator} />
                          </div>
                        </td>
                        <td className="px-3 py-3">
                          <QueueProductCell row={row} />
                        </td>
                        <td className="px-3 py-3">
                          <span className="text-xs font-semibold text-zinc-700">
                            {row.journeyLabel}
                          </span>
                        </td>
                        <td className="px-3 py-3">
                          <span
                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ${
                              STATUS_STYLES[row.status] ??
                              "bg-zinc-50 text-zinc-700 ring-zinc-200"
                            }`}
                          >
                            {row.currentStage}
                          </span>
                        </td>
                        <td className="px-3 py-3 text-sm tabular-nums text-zinc-600">
                          {row.ageLabel}
                        </td>
                        <td className="px-3 py-3 text-sm font-medium text-zinc-800">
                          {row.requiredAction}
                        </td>
                        <td className="px-3 py-3 text-sm text-zinc-600">{row.assignedLabel}</td>
                        <td className="px-3 py-3">
                          <Link
                            href={`/admin/fulfillments/${encodeURIComponent(row.id)}`}
                            className="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-zinc-900 px-3 py-2 text-xs font-semibold text-white hover:bg-zinc-800"
                          >
                            Open
                          </Link>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            <div className="space-y-3 p-4 lg:hidden">
              {queueRows.map((row) => (
                <QueueMobileCard
                  key={row.id}
                  row={row}
                  selected={selectedIds.has(row.id)}
                  onToggleSelected={() =>
                    setSelectedIds((current) => toggleTableSelection(current, row.id))
                  }
                />
              ))}
            </div>
          </>
        )}

        {!loading ? (
          <div className="flex flex-col gap-3 border-t border-zinc-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-zinc-500">
              Showing page {page} of {lastPage} · {totalRows} fulfilment
              {totalRows === 1 ? "" : "s"} total
              {selection.selectedCount > 0
                ? ` · ${selection.selectedCount} selected on this page`
                : ""}
            </p>
            <div className="flex items-center gap-2">
              <button
                type="button"
                disabled={page <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                className="min-h-10 rounded-lg border border-zinc-200 px-3 py-2 text-xs font-semibold text-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Previous
              </button>
              <button
                type="button"
                disabled={page >= lastPage}
                onClick={() => setPage((current) => Math.min(lastPage, current + 1))}
                className="min-h-10 rounded-lg border border-zinc-200 px-3 py-2 text-xs font-semibold text-zinc-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
