import { getAdminBrandBySlug } from "@/lib/admin/brand-options";
import {
  getAdminOrderListSummary,
  matchesAdminOrderSearch,
  orderMatchesBrandFilter,
  orderMatchesCategoryFilter,
  orderMatchesSourceFilter,
} from "@/lib/admin/order-list-summary";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import {
  ADMIN_ORDER_LIST_FILTERS,
  type AdminOrderListFilter,
  filterOrdersByListFilter,
} from "@/lib/payment/order-filters";
import type { Order } from "@/lib/types/order";

export type AdminOrderSourceFilter = "all" | "china" | "local";

export type AdminOrderQueryParams = {
  search?: string;
  source?: AdminOrderSourceFilter;
  category?: string;
  brand?: string;
  status?: AdminOrderListFilter;
};

export type AdminOrderFilterOptions = {
  brands: Array<{ slug: string; label: string; count: number }>;
  categories: Array<{ slug: string; label: string; count: number }>;
};

function normalizeOptionalFilter(value: string | null | undefined): string | undefined {
  const trimmed = value?.trim();
  if (!trimmed || trimmed === "all") {
    return undefined;
  }
  return trimmed;
}

export function parseAdminOrderQueryParams(url: URL): AdminOrderQueryParams {
  const statusRaw = url.searchParams.get("status")?.trim();
  const status = ADMIN_ORDER_LIST_FILTERS.some((entry) => entry.id === statusRaw)
    ? (statusRaw as AdminOrderListFilter)
    : undefined;

  const sourceRaw = url.searchParams.get("source")?.trim();
  const source =
    sourceRaw === "china" || sourceRaw === "local" ? sourceRaw : sourceRaw === "all" ? "all" : undefined;

  return {
    search: url.searchParams.get("search")?.trim() || undefined,
    source,
    category: normalizeOptionalFilter(url.searchParams.get("category") ?? undefined),
    brand: normalizeOptionalFilter(url.searchParams.get("brand") ?? undefined),
    status,
  };
}

export function filterAdminOrders(orders: Order[], params: AdminOrderQueryParams): Order[] {
  const status = params.status ?? "all";
  let result = filterOrdersByListFilter(orders, status);

  if (params.source && params.source !== "all") {
    result = result.filter((order) => orderMatchesSourceFilter(order, params.source!));
  }

  if (params.brand) {
    result = result.filter((order) => orderMatchesBrandFilter(order, params.brand!));
  }

  if (params.category) {
    result = result.filter((order) => orderMatchesCategoryFilter(order, params.category!));
  }

  if (params.search) {
    result = result.filter((order) => matchesAdminOrderSearch(order, params.search!));
  }

  return result;
}

export function extractAdminOrderFilterOptions(orders: Order[]): AdminOrderFilterOptions {
  const brandCounts = new Map<string, { label: string; count: number }>();
  const categoryCounts = new Map<string, { label: string; count: number }>();

  for (const order of orders) {
    const summary = getAdminOrderListSummary(order);

    for (const slug of summary.brandSlugs) {
      const existing = brandCounts.get(slug);
      const label = getAdminBrandBySlug(slug)?.name ?? summary.brandNames[0] ?? slug;
      brandCounts.set(slug, { label, count: (existing?.count ?? 0) + 1 });
    }

    for (const slug of summary.categorySlugs) {
      const existing = categoryCounts.get(slug);
      const label = getCategoryBySlug(slug)?.name ?? slug;
      categoryCounts.set(slug, { label, count: (existing?.count ?? 0) + 1 });
    }
  }

  const brands = [...brandCounts.entries()]
    .map(([slug, meta]) => ({ slug, label: meta.label, count: meta.count }))
    .sort((a, b) => a.label.localeCompare(b.label));

  const categories = [...categoryCounts.entries()]
    .map(([slug, meta]) => ({ slug, label: meta.label, count: meta.count }))
    .sort((a, b) => a.label.localeCompare(b.label));

  return { brands, categories };
}

export function countAdminOrdersForChip(
  orders: Order[],
  chip: Pick<AdminOrderQueryParams, "source" | "brand" | "category"> & {
    status?: AdminOrderListFilter;
  },
): number {
  return filterAdminOrders(orders, chip).length;
}
