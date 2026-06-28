import { formatBrandDisplayName, getBrandBySlug } from "@/lib/catalog/brands";
import {
  getAdminOrderListSummary,
  getOrderLineFilterMeta,
  resolveAdminOrderType,
} from "@/lib/admin/order-list-summary";
import type { Order, OrderLineItem } from "@/lib/types/order";

export type AdminOrderSourceTone = "china" | "dar" | "brand";

export type AdminOrderSourceBadgeData = {
  label: string;
  tone: AdminOrderSourceTone;
};

export function resolveAdminOrderSourceBadge(order: Order): AdminOrderSourceBadgeData {
  if (resolveAdminOrderType(order) === "china") {
    return { label: "China Order", tone: "china" };
  }

  const summary = getAdminOrderListSummary(order);
  if (summary.brandNames.length === 1) {
    return { label: summary.brandNames[0]!, tone: "brand" };
  }

  const primaryBrandSlug = summary.brandSlugs[0];
  if (primaryBrandSlug) {
    const brand = getBrandBySlug(primaryBrandSlug);
    if (brand) {
      return { label: formatBrandDisplayName(brand.name), tone: "brand" };
    }
  }

  return { label: "Buy from Dar", tone: "dar" };
}

export function resolveAdminLineItemSourceBadge(
  order: Order,
  item: OrderLineItem,
): AdminOrderSourceBadgeData {
  const meta = getOrderLineFilterMeta(order, item);

  if (meta.source === "china") {
    return { label: "China Order", tone: "china" };
  }

  if (meta.brandSlug) {
    const brand = getBrandBySlug(meta.brandSlug);
    if (brand) {
      return { label: formatBrandDisplayName(brand.name), tone: "brand" };
    }
  }

  if (meta.brand?.trim()) {
    return { label: meta.brand.trim(), tone: "brand" };
  }

  return { label: "Buy from Dar", tone: "dar" };
}
