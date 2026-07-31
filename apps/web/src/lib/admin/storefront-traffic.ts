import type { AdminStorefrontTrafficMetrics } from "@/lib/api/admin-reporting";

export function formatStorefrontGrowthPercent(value: number): string {
  if (value === 0) {
    return "0%";
  }

  const prefix = value > 0 ? "+" : "";
  return `${prefix}${value.toFixed(1)}%`;
}

export function storefrontGrowthAccent(value: number): string {
  if (value > 0) {
    return "text-emerald-600";
  }

  if (value < 0) {
    return "text-red-600";
  }

  return "text-zinc-500";
}

export function hasStorefrontTrafficData(metrics: AdminStorefrontTrafficMetrics): boolean {
  return (
    metrics.visitors_today > 0 ||
    metrics.sessions_today > 0 ||
    metrics.top_pages.length > 0 ||
    metrics.top_products.length > 0 ||
    metrics.top_searches.length > 0
  );
}

export function storefrontTrafficEmptyMessage(metrics: AdminStorefrontTrafficMetrics): string {
  if (hasStorefrontTrafficData(metrics)) {
    return "";
  }

  return "No storefront traffic recorded for this period yet.";
}
