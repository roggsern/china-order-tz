import { getAdminBrandBySlug } from "@/lib/admin/brand-options";
import {
  buildAdminSearchHaystack,
  matchesAdminSearchTerms,
} from "@/lib/admin/admin-search-utils";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import type { ProductImage } from "@/lib/types/catalog";
import type { Order, OrderLineItem, AdminOrderListSummary, AdminOrderType } from "@/lib/types/order";

export type { AdminOrderListSummary, AdminOrderType } from "@/lib/types/order";

const FALLBACK_IMAGE: ProductImage = {
  id: 0,
  emoji: "📦",
  gradient: "from-zinc-200 to-zinc-300",
  alt: "Product",
};

export type OrderLineFilterMeta = {
  brand?: string;
  brandSlug?: string;
  categorySlug?: string;
  categoryName?: string;
  source: "china" | "local";
};

function resolveLineItemOrigin(item: OrderLineItem): "china" | "tz" {
  if (item.origin) {
    return item.origin;
  }

  const method = item.shipping?.method ?? item.shippingMethod;
  return method === "local_delivery" ? "tz" : "china";
}

function resolveBrandSlug(brand?: string, brandSlug?: string): string | undefined {
  if (brandSlug) {
    return brandSlug;
  }

  if (!brand) {
    return undefined;
  }

  const normalized = brand.trim().toLowerCase();
  const fromOptions = getAdminBrandBySlug(normalized.replace(/\s+/g, "-"));
  if (fromOptions) {
    return fromOptions.slug;
  }

  return normalized.replace(/\s+/g, "-");
}

export function getOrderLineFilterMeta(order: Order, item: OrderLineItem): OrderLineFilterMeta {
  const cartItem = order.cartSnapshot?.items?.find(
    (entry) => entry.id === item.id || entry.productId === item.productId,
  );

  const categorySlug = item.categorySlug ?? cartItem?.categorySlug;
  const brand = item.brand ?? cartItem?.brand;
  const brandSlug = resolveBrandSlug(brand, item.brandSlug ?? cartItem?.brandSlug);
  const category = categorySlug ? getCategoryBySlug(categorySlug) : undefined;
  const origin = resolveLineItemOrigin(item);

  return {
    brand,
    brandSlug,
    categorySlug,
    categoryName: category?.name,
    source: origin === "china" ? "china" : "local",
  };
}

export function resolveAdminOrderType(order: Order): AdminOrderType {
  const hasImportedItems = order.items.some((item) => resolveLineItemOrigin(item) === "china");
  return hasImportedItems ? "china" : "dar";
}

export function resolveAdminOrderSource(order: Order): "china" | "local" {
  return resolveAdminOrderType(order) === "china" ? "china" : "local";
}

export function getAdminOrderTypeLabel(orderType: AdminOrderType): string {
  return orderType === "china" ? "China Order" : "Buy from Dar";
}

export function buildAdminOrderListSummary(order: Order): AdminOrderListSummary {
  const productNames = order.items.map((item) => item.name).filter(Boolean);
  const primary = order.items[0];
  const categorySlugs = new Set<string>();
  const categoryNames = new Set<string>();
  const brandSlugs = new Set<string>();
  const brandNames = new Set<string>();

  for (const item of order.items) {
    const meta = getOrderLineFilterMeta(order, item);
    if (meta.categorySlug) {
      categorySlugs.add(meta.categorySlug);
    }
    if (meta.categoryName) {
      categoryNames.add(meta.categoryName);
    }
    if (meta.brandSlug) {
      brandSlugs.add(meta.brandSlug);
    }
    if (meta.brand) {
      brandNames.add(meta.brand);
    }
  }

  const source = resolveAdminOrderSource(order);

  return {
    orderType: source === "china" ? "china" : "dar",
    source,
    primaryProductName: primary?.name ?? "No products",
    primaryProductImage: primary?.image ?? FALLBACK_IMAGE,
    productNames,
    categorySlugs: [...categorySlugs],
    categoryNames: [...categoryNames],
    brandSlugs: [...brandSlugs],
    brandNames: [...brandNames],
    additionalItemCount: Math.max(0, order.items.length - 1),
  };
}

export function getAdminOrderListSummary(order: Order): AdminOrderListSummary {
  return order.adminListSummary ?? buildAdminOrderListSummary(order);
}

export function attachAdminOrderListSummary(order: Order): Order {
  return {
    ...order,
    adminListSummary: buildAdminOrderListSummary(order),
  };
}

export function matchesAdminOrderSearch(order: Order, query: string): boolean {
  const summary = getAdminOrderListSummary(order);
  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();

  const haystack = buildAdminSearchHaystack([
    order.id,
    order.orderNumber,
    customerName,
    ...summary.productNames,
    ...summary.categoryNames,
    ...summary.categorySlugs,
    ...summary.brandNames,
  ]);

  return matchesAdminSearchTerms(haystack, query);
}

export function formatAdminOrderProductTooltip(order: Order): string {
  if (order.items.length === 0) {
    return "No products";
  }

  return order.items
    .map((item) => (item.quantity > 1 ? `${item.name} ×${item.quantity}` : item.name))
    .join("\n");
}

export function orderMatchesBrandFilter(order: Order, brandSlug: string): boolean {
  if (!brandSlug || brandSlug === "all") {
    return true;
  }

  const summary = getAdminOrderListSummary(order);
  return summary.brandSlugs.includes(brandSlug);
}

export function orderMatchesCategoryFilter(order: Order, categorySlug: string): boolean {
  if (!categorySlug || categorySlug === "all") {
    return true;
  }

  const summary = getAdminOrderListSummary(order);
  return summary.categorySlugs.includes(categorySlug);
}

export function orderMatchesSourceFilter(order: Order, source: "all" | "china" | "local"): boolean {
  if (source === "all") {
    return true;
  }

  return getAdminOrderListSummary(order).source === source;
}
