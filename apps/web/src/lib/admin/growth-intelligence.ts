import type { AdminGrowthIntelligenceMetrics } from "@/lib/api/admin-reporting";

export function formatGrowthHealthStatus(status: AdminGrowthIntelligenceMetrics["health_status"]): string {
  switch (status) {
    case "healthy":
      return "Healthy";
    case "watch":
      return "Watch";
    case "at_risk":
      return "At risk";
    default:
      return status;
  }
}

export function growthHealthAccent(status: AdminGrowthIntelligenceMetrics["health_status"]): string {
  switch (status) {
    case "healthy":
      return "text-emerald-600";
    case "watch":
      return "text-amber-600";
    case "at_risk":
      return "text-red-600";
    default:
      return "text-zinc-500";
  }
}

export function growthSeverityBadgeClass(severity: "HIGH" | "MEDIUM" | "LOW"): string {
  switch (severity) {
    case "HIGH":
      return "border-red-200 bg-red-50 text-red-700";
    case "MEDIUM":
      return "border-amber-200 bg-amber-50 text-amber-800";
    case "LOW":
      return "border-emerald-200 bg-emerald-50 text-emerald-700";
    default:
      return "border-zinc-200 bg-zinc-50 text-zinc-700";
  }
}

export function hasGrowthIntelligenceData(metrics: AdminGrowthIntelligenceMetrics): boolean {
  return (
    metrics.health_summary.visitors > 0 ||
    metrics.alerts.length > 0 ||
    metrics.health_summary.buyers > 0
  );
}

export function growthIntelligenceEmptyMessage(metrics: AdminGrowthIntelligenceMetrics): string {
  if (hasGrowthIntelligenceData(metrics)) {
    return "";
  }

  return "Not enough storefront activity yet to generate growth insights for this period.";
}

export function partitionGrowthAlerts(metrics: AdminGrowthIntelligenceMetrics): {
  warnings: AdminGrowthIntelligenceMetrics["alerts"];
  opportunities: AdminGrowthIntelligenceMetrics["alerts"];
} {
  return {
    warnings: metrics.alerts.filter((alert) => alert.category === "warning"),
    opportunities: metrics.alerts.filter((alert) => alert.category === "opportunity"),
  };
}

export function formatGrowthPercent(value: number): string {
  const prefix = value > 0 ? "+" : "";
  return `${prefix}${value.toFixed(1)}%`;
}

export function formatGrowthPoints(value: number): string {
  const prefix = value > 0 ? "+" : "";
  return `${prefix}${value.toFixed(1)} pts`;
}
