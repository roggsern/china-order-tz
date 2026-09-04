"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import type { Order } from "@/lib/types/order";
import {
  extractAdminOrderFilterOptions,
  filterAdminOrders,
  type AdminOrderSourceFilter,
} from "@/lib/admin/order-query-filters";
import { ADMIN_SEARCH_DEBOUNCE_MS } from "@/lib/admin/admin-search-utils";
import {
  ADMIN_ORDER_LIST_FILTERS,
  type AdminOrderListFilter,
} from "@/lib/payment/order-filters";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { AdminFilterChips } from "@/components/admin/AdminFilterChips";
import { formatPrice } from "@/lib/catalog/utils";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderStatusBadge } from "@/components/order/OrderStatusBadge";
import { OrderLiveStatusIndicator } from "@/components/admin/OrderLiveStatusIndicator";
import { AdminOrderProductCell } from "@/components/admin/AdminOrderProductCell";
import { ADMIN_ORDERS_DEFAULT_PER_PAGE } from "@/lib/admin/admin-orders-pagination";

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

function shortenOrderId(orderId: string): string {
  return orderId.slice(0, 8).toUpperCase();
}

export function AdminOrderTable() {
  const {
    orders,
    listMeta,
    listQuery,
    isHydrated,
    isListLoading,
    newOrderIds,
    setListPage,
    setListFilters,
  } = useAdminOrders();
  const [activeFilter, setActiveFilter] = useState<AdminOrderListFilter>(listQuery.status ?? "all");
  const [sourceFilter, setSourceFilter] = useState<AdminOrderSourceFilter>(listQuery.source ?? "all");
  const [brandFilter, setBrandFilter] = useState("all");
  const [categoryFilter, setCategoryFilter] = useState("all");
  const [search, setSearch] = useState(listQuery.search ?? "");
  const debouncedSearch = useDebouncedValue(search, ADMIN_SEARCH_DEBOUNCE_MS);

  useEffect(() => {
    setListFilters({
      status: activeFilter,
      source: sourceFilter,
      search: debouncedSearch,
    });
  }, [activeFilter, sourceFilter, debouncedSearch, setListFilters]);

  useEffect(() => {
    setBrandFilter("all");
    setCategoryFilter("all");
  }, [activeFilter, sourceFilter, debouncedSearch]);

  const pageOrders = useMemo(
    () =>
      filterAdminOrders(orders, {
        brand: brandFilter === "all" ? undefined : brandFilter,
        category: categoryFilter === "all" ? undefined : categoryFilter,
      }),
    [orders, brandFilter, categoryFilter],
  );

  const isSearchPending = search.trim() !== debouncedSearch.trim();
  const requestedPage = listQuery.page || listMeta.current_page || 1;
  const currentPage = listMeta.current_page || requestedPage;
  const totalPages = Math.max(1, listMeta.last_page || 1);
  const perPage = listMeta.per_page || ADMIN_ORDERS_DEFAULT_PER_PAGE;
  const pageScopedFilters = brandFilter !== "all" || categoryFilter !== "all";
  const filterOptions = useMemo(() => extractAdminOrderFilterOptions(orders), [orders]);

  if (!isHydrated) {
    return (
      <div className="admin-card p-8">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-6 h-64 animate-pulse rounded-lg bg-zinc-50" />
      </div>
    );
  }

  const activeFilterMeta = ADMIN_ORDER_LIST_FILTERS.find((entry) => entry.id === activeFilter);

  const statusChips = ADMIN_ORDER_LIST_FILTERS.map((filter) => ({
    id: filter.id,
    label: filter.label,
  }));

  const sourceChips = [
    { id: "all", label: "All Orders" },
    { id: "china", label: "Order from China" },
    { id: "local", label: "Buy From TZ" },
  ];

  const brandChips = [
    {
      id: "all",
      label: "All Brands",
      count: filterAdminOrders(orders, {
        brand: undefined,
        category: categoryFilter === "all" ? undefined : categoryFilter,
      }).length,
    },
    ...filterOptions.brands.map((brand) => ({
      id: brand.slug,
      label: brand.label,
      count: filterAdminOrders(orders, {
        brand: brand.slug,
        category: categoryFilter === "all" ? undefined : categoryFilter,
      }).length,
    })),
  ];

  const categoryChips = [
    {
      id: "all",
      label: "All Categories",
      count: filterAdminOrders(orders, {
        brand: brandFilter === "all" ? undefined : brandFilter,
      }).length,
    },
    ...filterOptions.categories.map((category) => ({
      id: category.slug,
      label: category.label,
      count: filterAdminOrders(orders, {
        brand: brandFilter === "all" ? undefined : brandFilter,
        category: category.slug,
      }).length,
    })),
  ];

  const paginationBar =
    listMeta.total > perPage ? (
      <div className="flex flex-col gap-3 border-t border-zinc-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-xs text-zinc-500">
          Showing {listMeta.from ?? (currentPage - 1) * perPage + 1}–
          {listMeta.to ?? Math.min(currentPage * perPage, listMeta.total)} of {listMeta.total}
        </p>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            disabled={requestedPage <= 1}
            onClick={() => setListPage(requestedPage - 1)}
            className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:opacity-40"
          >
            Previous
          </button>
          {totalPages <= 10
            ? Array.from({ length: totalPages }, (_, index) => index + 1).map((pageNumber) => (
                <button
                  key={pageNumber}
                  type="button"
                  disabled={pageNumber === requestedPage && isListLoading}
                  onClick={() => setListPage(pageNumber)}
                  aria-current={pageNumber === requestedPage ? "page" : undefined}
                  className={`rounded-lg border px-3 py-1.5 text-xs font-medium transition ${
                    pageNumber === requestedPage
                      ? "border-zinc-900 bg-zinc-900 text-white"
                      : "border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50"
                  }`}
                >
                  {pageNumber}
                </button>
              ))
            : null}
          <button
            type="button"
            disabled={requestedPage >= totalPages}
            onClick={() => setListPage(requestedPage + 1)}
            className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    ) : null;

  return (
    <div className="admin-card overflow-hidden">
      <div className="space-y-4 border-b border-zinc-200 p-4">
        <AdminFilterChips
          chips={statusChips}
          activeId={activeFilter}
          onChange={(id) => setActiveFilter(id as AdminOrderListFilter)}
          ariaLabel="Order status filters"
        />

        <AdminFilterChips
          chips={sourceChips}
          activeId={sourceFilter}
          onChange={(id) => setSourceFilter(id as AdminOrderSourceFilter)}
          ariaLabel="Order source filters"
        />

        {brandChips.length > 1 && (
          <AdminFilterChips
            chips={brandChips}
            activeId={brandFilter}
            onChange={setBrandFilter}
            ariaLabel="Brand filters"
          />
        )}

        {categoryChips.length > 1 && (
          <AdminFilterChips
            chips={categoryChips}
            activeId={categoryFilter}
            onChange={setCategoryFilter}
            ariaLabel="Category filters"
          />
        )}

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="relative max-w-md flex-1">
            <input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search order number, customer name, email, or phone"
              className="admin-input w-full"
              aria-label="Search orders by number, customer name, email, or phone"
            />
            {isSearchPending && (
              <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-medium text-zinc-400">
                …
              </span>
            )}
          </div>
          <p className="text-xs text-zinc-500 sm:ml-auto">
            {pageScopedFilters
              ? `${pageOrders.length} on this page`
              : `${listMeta.total} result${listMeta.total === 1 ? "" : "s"}`}
            {totalPages > 1 ? ` · page ${requestedPage} of ${totalPages}` : ""}
            {isListLoading ? ` · Loading page ${requestedPage}…` : ""}
          </p>
        </div>
      </div>

      <div role="tabpanel" aria-busy={isListLoading} className="relative">
        {pageOrders.length === 0 ? (
          <>
            <div className="flex flex-col items-center px-6 py-16 text-center">
              <span className="text-4xl" aria-hidden>
                📋
              </span>
              <p className="mt-4 text-sm font-medium text-zinc-700">
                No {activeFilterMeta?.label.toLowerCase()} orders
              </p>
              <p className="mt-1 text-xs text-zinc-500">{activeFilterMeta?.description}</p>
            </div>
            {paginationBar}
          </>
        ) : (
          <>
            <div className="space-y-3 p-4 lg:hidden">
              {pageOrders.map((order) => (
                <OrderCard key={order.id} order={order} isNew={newOrderIds.has(order.id)} />
              ))}
            </div>

            <div className="hidden overflow-x-auto lg:block">
              <table className="w-full min-w-[960px] text-left text-sm">
                <thead>
                  <tr className="border-b border-zinc-200 bg-zinc-50/80">
                    <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                      Order ID
                    </th>
                    <th className="min-w-[240px] px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                      Product
                    </th>
                    <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                      Customer
                    </th>
                    <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                      Total
                    </th>
                    <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                      Payment Status
                    </th>
                    <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                      Order Status
                    </th>
                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">
                      Action
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-zinc-100">
                  {pageOrders.map((order) => (
                    <OrderRow key={order.id} order={order} isNew={newOrderIds.has(order.id)} />
                  ))}
                </tbody>
              </table>
            </div>

            {paginationBar}
          </>
        )}
      </div>
    </div>
  );
}

function OrderRow({ order, isNew }: { order: Order; isNew?: boolean }) {
  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();

  return (
    <tr
      className={`transition hover:bg-zinc-50/80 ${isNew ? "admin-order-row-new bg-[#c9a227]/10" : ""}`}
    >
      <td className="px-4 py-3">
        <div className="flex items-center gap-2">
          <OrderLiveStatusIndicator order={order} />
          <div>
            <Link
              href={`/admin/orders/${order.id}`}
              className="font-mono text-sm font-semibold text-zinc-900 hover:text-[#8b6914] hover:underline"
              title={order.id}
            >
              {shortenOrderId(order.id)}
            </Link>
            <p className="mt-0.5 font-mono text-xs text-zinc-400">{order.orderNumber}</p>
          </div>
        </div>
      </td>
      <td className="px-4 py-3">
        <AdminOrderProductCell order={order} />
      </td>
      <td className="px-4 py-3">
        <p className="font-medium text-zinc-900">{customerName || "—"}</p>
        <p className="text-xs text-zinc-500">{order.customer.phone || order.customer.email}</p>
      </td>
      <td className="px-4 py-3 font-semibold text-zinc-900">
        {formatPrice(order.grandTotal ?? order.totals.grandTotal)}
      </td>
      <td className="px-4 py-3">
        <PaymentStatusBadge status={order.paymentStatus} size="sm" />
      </td>
      <td className="px-4 py-3">
        <OrderStatusBadge status={order.status} size="sm" />
      </td>
      <td className="px-4 py-3 text-right">
        <Link
          href={`/admin/orders/${order.id}`}
          className="inline-flex rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-50"
        >
          View order
        </Link>
      </td>
    </tr>
  );
}

function OrderCard({ order, isNew }: { order: Order; isNew?: boolean }) {
  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();

  return (
    <article
      className={`overflow-hidden rounded-2xl border bg-white p-4 shadow-sm ${
        isNew ? "admin-order-row-new border-[#c9a227]/50 bg-[#c9a227]/5" : "border-zinc-200"
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-2">
          <OrderLiveStatusIndicator order={order} size="md" />
          <div className="min-w-0">
            <Link
              href={`/admin/orders/${order.id}`}
              className="font-mono text-sm font-bold text-zinc-900 hover:text-[#8b6914]"
            >
              {shortenOrderId(order.id)}
            </Link>
            <p className="mt-0.5 truncate font-mono text-xs text-zinc-400">{order.orderNumber}</p>
            <p className="mt-1 truncate text-sm font-medium text-zinc-900">{customerName || "—"}</p>
            <p className="text-xs text-zinc-500">{formatOrderDate(order.createdAt)}</p>
          </div>
        </div>
        <p className="shrink-0 text-base font-bold text-zinc-900">
          {formatPrice(order.grandTotal ?? order.totals.grandTotal)}
        </p>
      </div>

      <div className="mt-3">
        <AdminOrderProductCell order={order} compact />
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <PaymentStatusBadge status={order.paymentStatus} size="sm" />
        <OrderStatusBadge status={order.status} size="sm" />
      </div>

      <div className="mt-4">
        <Link href={`/admin/orders/${order.id}`} className="admin-btn-secondary inline-flex text-xs">
          View order
        </Link>
      </div>
    </article>
  );
}
