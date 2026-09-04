import type { AdminOrderListFilter } from "@/lib/payment/order-filters";
import type { AdminOrderSourceFilter } from "@/lib/admin/order-query-filters";

export const ADMIN_ORDERS_DEFAULT_PER_PAGE = 20;
export const ADMIN_ORDERS_MAX_PER_PAGE = 100;

export type AdminOrdersListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
};

export type AdminOrdersListQuery = {
  page: number;
  perPage: number;
  status?: AdminOrderListFilter;
  search?: string;
  source?: AdminOrderSourceFilter;
};

export function emptyAdminOrdersListMeta(
  perPage: number = ADMIN_ORDERS_DEFAULT_PER_PAGE,
): AdminOrdersListMeta {
  return {
    current_page: 1,
    last_page: 1,
    per_page: perPage,
    total: 0,
    from: null,
    to: null,
  };
}

export function clampAdminOrdersPerPage(value: number | null | undefined): number {
  if (typeof value !== "number" || !Number.isFinite(value) || value < 1) {
    return ADMIN_ORDERS_DEFAULT_PER_PAGE;
  }

  return Math.min(ADMIN_ORDERS_MAX_PER_PAGE, Math.floor(value));
}

export function clampAdminOrdersPage(page: number, lastPage: number): number {
  if (!Number.isFinite(page) || page < 1) {
    return 1;
  }

  return Math.min(page, Math.max(1, lastPage));
}

export function pageCountFromTotal(total: number, perPage: number): number {
  const size = Math.max(1, perPage);
  return Math.max(1, Math.ceil(Math.max(0, total) / size));
}

export function mapSourceFilterToCommerceChannel(
  source?: AdminOrderSourceFilter | string | null,
): string | undefined {
  if (source === "china") {
    return "CHINA_IMPORT";
  }
  if (source === "local") {
    return "TZ_LOCAL";
  }
  return undefined;
}

export function mapStatusFilterToLaravelStatus(
  status?: AdminOrderListFilter | string | null,
): string | undefined {
  if (!status || status === "all") {
    return undefined;
  }
  return status;
}

function asFiniteNumber(value: unknown): number | null {
  if (typeof value === "number" && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

export function extractLaravelPaginationMeta(
  payload: unknown,
  fallback: { page: number; perPage: number; itemCount: number },
): AdminOrdersListMeta {
  const root =
    payload && typeof payload === "object" ? (payload as Record<string, unknown>) : {};
  const nestedMeta =
    root.meta && typeof root.meta === "object" ? (root.meta as Record<string, unknown>) : {};

  const perPage =
    asFiniteNumber(nestedMeta.per_page) ??
    asFiniteNumber(root.per_page) ??
    fallback.perPage;
  const total =
    asFiniteNumber(nestedMeta.total) ?? asFiniteNumber(root.total) ?? fallback.itemCount;
  const currentPage =
    asFiniteNumber(nestedMeta.current_page) ?? asFiniteNumber(root.current_page) ?? fallback.page;
  const lastPage =
    asFiniteNumber(nestedMeta.last_page) ??
    asFiniteNumber(root.last_page) ??
    pageCountFromTotal(total, perPage);
  const from = asFiniteNumber(nestedMeta.from) ?? asFiniteNumber(root.from);
  const to = asFiniteNumber(nestedMeta.to) ?? asFiniteNumber(root.to);

  return {
    current_page: currentPage,
    last_page: lastPage,
    per_page: perPage,
    total,
    from,
    to,
  };
}

export function paginateLocalOrders<T>(
  orders: T[],
  page: number,
  perPage: number,
): { items: T[]; meta: AdminOrdersListMeta } {
  const size = clampAdminOrdersPerPage(perPage);
  const total = orders.length;
  const lastPage = pageCountFromTotal(total, size);
  const current = clampAdminOrdersPage(page, lastPage);
  const start = (current - 1) * size;
  const items = orders.slice(start, start + size);
  const from = items.length === 0 ? null : start + 1;
  const to = items.length === 0 ? null : start + items.length;

  return {
    items,
    meta: {
      current_page: current,
      last_page: lastPage,
      per_page: size,
      total,
      from,
      to,
    },
  };
}

export function buildAdminOrdersSearchParams(query: AdminOrdersListQuery): URLSearchParams {
  const search = new URLSearchParams();
  const page = query.page > 0 ? query.page : 1;
  const perPage = clampAdminOrdersPerPage(query.perPage);
  search.set("page", String(page));
  search.set("per_page", String(perPage));

  const status = mapStatusFilterToLaravelStatus(query.status);
  if (status) {
    search.set("status", status);
  }

  const q = query.search?.trim();
  if (q) {
    search.set("q", q);
    search.set("search", q);
  }

  const channel = mapSourceFilterToCommerceChannel(query.source);
  if (channel) {
    search.set("commerce_channel", channel);
    search.set("source", query.source ?? "");
  }

  return search;
}

export function defaultAdminOrdersListQuery(): AdminOrdersListQuery {
  return {
    page: 1,
    perPage: ADMIN_ORDERS_DEFAULT_PER_PAGE,
  };
}

export function applyAdminOrdersListPage(
  current: AdminOrdersListQuery,
  page: number,
): AdminOrdersListQuery {
  const nextPage = page > 0 ? Math.floor(page) : 1;
  if (current.page === nextPage) {
    return current;
  }
  return { ...current, page: nextPage };
}

export function applyAdminOrdersListFilters(
  current: AdminOrdersListQuery,
  filters: {
    status?: AdminOrderListFilter;
    search?: string;
    source?: AdminOrderSourceFilter;
  },
): AdminOrdersListQuery {
  const nextStatus = filters.status ?? current.status;
  const nextSearch = filters.search ?? current.search;
  const nextSource = filters.source ?? current.source;
  if (
    current.status === nextStatus &&
    current.search === nextSearch &&
    current.source === nextSource
  ) {
    return current;
  }

  return {
    ...current,
    page: 1,
    status: nextStatus,
    search: nextSearch,
    source: nextSource,
  };
}

let activeAdminOrdersListQuery: AdminOrdersListQuery = defaultAdminOrdersListQuery();

export function getActiveAdminOrdersListQuery(): AdminOrdersListQuery {
  return activeAdminOrdersListQuery;
}

export function setActiveAdminOrdersListQuery(query: AdminOrdersListQuery): void {
  activeAdminOrdersListQuery = {
    page: query.page > 0 ? query.page : 1,
    perPage: clampAdminOrdersPerPage(query.perPage),
    status: query.status,
    search: query.search,
    source: query.source,
  };
}
