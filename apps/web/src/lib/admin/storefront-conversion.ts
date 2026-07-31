import type { AdminStorefrontConversionMetrics } from "@/lib/api/admin-reporting";

export function formatConversionRate(value: number): string {
  return `${value.toFixed(1)}%`;
}

export function hasStorefrontConversionData(metrics: AdminStorefrontConversionMetrics): boolean {
  return (
    metrics.funnel.visitors > 0 ||
    metrics.funnel.buyers > 0 ||
    metrics.product_insights.length > 0
  );
}

export function storefrontConversionEmptyMessage(
  metrics: AdminStorefrontConversionMetrics,
): string {
  if (hasStorefrontConversionData(metrics)) {
    return "";
  }

  return "No storefront conversion activity recorded for this period yet.";
}

export const STOREFRONT_FUNNEL_STAGES = [
  { key: "visitors", label: "Visitors" },
  { key: "product_viewers", label: "Product viewers" },
  { key: "cart_users", label: "Cart users" },
  { key: "checkout_users", label: "Checkout users" },
  { key: "buyers", label: "Buyers" },
] as const;

export const STOREFRONT_CONVERSION_CARDS = [
  { key: "visitor_to_product_view", label: "Visitor → product view" },
  { key: "product_view_to_cart", label: "Product view → cart" },
  { key: "cart_to_checkout", label: "Cart → checkout" },
  { key: "checkout_to_purchase", label: "Checkout → purchase" },
  { key: "visitor_to_purchase", label: "Visitor → purchase" },
] as const;

export function funnelStageValue(
  metrics: AdminStorefrontConversionMetrics,
  key: (typeof STOREFRONT_FUNNEL_STAGES)[number]["key"],
): number {
  return metrics.funnel[key];
}

export function conversionCardValue(
  metrics: AdminStorefrontConversionMetrics,
  key: (typeof STOREFRONT_CONVERSION_CARDS)[number]["key"],
): number {
  return metrics.conversion_rates[key];
}

export function funnelStageWidth(
  metrics: AdminStorefrontConversionMetrics,
  key: (typeof STOREFRONT_FUNNEL_STAGES)[number]["key"],
): number {
  const max = Math.max(metrics.funnel.visitors, 1);
  return Math.max(8, Math.round((metrics.funnel[key] / max) * 100));
}
