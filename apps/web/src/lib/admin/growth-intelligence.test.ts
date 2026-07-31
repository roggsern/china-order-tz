import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { AdminGrowthIntelligenceMetrics } from "@/lib/api/admin-reporting";
import {
  formatGrowthHealthStatus,
  formatGrowthPercent,
  formatGrowthPoints,
  growthHealthAccent,
  growthIntelligenceEmptyMessage,
  growthSeverityBadgeClass,
  hasGrowthIntelligenceData,
  partitionGrowthAlerts,
} from "@/lib/admin/growth-intelligence";
import {
  getAdminRefreshPolicy,
  resolveAdminRefreshIntervalMs,
} from "@/lib/admin/admin-refresh-policy";

function emptyMetrics(
  overrides?: Partial<AdminGrowthIntelligenceMetrics>,
): AdminGrowthIntelligenceMetrics {
  return {
    health_status: "healthy",
    health_summary: {
      visitors: 0,
      buyers: 0,
      visitor_to_purchase: 0,
      visitors_change_percent: 0,
      conversion_change_points: 0,
      warning_count: 0,
      opportunity_count: 0,
      high_severity_count: 0,
    },
    growth_comparisons: {
      visitors_current: 0,
      visitors_previous: 0,
      visitors_change_percent: 0,
      buyers_current: 0,
      buyers_previous: 0,
      buyers_change_percent: 0,
      conversion_rate_current: 0,
      conversion_rate_previous: 0,
      conversion_change_points: 0,
    },
    alerts: [],
    ...overrides,
  };
}

describe("growth-intelligence helpers", () => {
  it("formats health status labels and accents", () => {
    assert.equal(formatGrowthHealthStatus("healthy"), "Healthy");
    assert.equal(growthHealthAccent("at_risk"), "text-red-600");
  });

  it("maps severity badge classes", () => {
    assert.match(growthSeverityBadgeClass("HIGH"), /red/);
    assert.match(growthSeverityBadgeClass("MEDIUM"), /amber/);
    assert.match(growthSeverityBadgeClass("LOW"), /emerald/);
  });

  it("detects empty growth intelligence payloads", () => {
    assert.equal(hasGrowthIntelligenceData(emptyMetrics()), false);
    assert.equal(
      hasGrowthIntelligenceData(emptyMetrics({ health_summary: { ...emptyMetrics().health_summary, visitors: 4 } })),
      true,
    );
  });

  it("returns empty-state copy when no activity exists", () => {
    assert.match(growthIntelligenceEmptyMessage(emptyMetrics()), /Not enough storefront activity/);
    assert.equal(
      growthIntelligenceEmptyMessage(emptyMetrics({ alerts: [{ type: "x", category: "warning", severity: "LOW", title: "t", message: "m" }] })),
      "",
    );
  });

  it("partitions warnings and opportunities", () => {
    const metrics = emptyMetrics({
      alerts: [
        { type: "conversion_drop", category: "warning", severity: "HIGH", title: "Drop", message: "Down" },
        { type: "conversion_improvement", category: "opportunity", severity: "LOW", title: "Up", message: "Better" },
      ],
    });

    const partitioned = partitionGrowthAlerts(metrics);
    assert.equal(partitioned.warnings.length, 1);
    assert.equal(partitioned.opportunities.length, 1);
  });

  it("formats growth percentages and points", () => {
    assert.equal(formatGrowthPercent(12.3), "+12.3%");
    assert.equal(formatGrowthPoints(-2.5), "-2.5 pts");
  });
});

describe("growth intelligence dashboard refresh", () => {
  it("uses MEDIUM_ACTIVITY for command center polling", () => {
    assert.equal(getAdminRefreshPolicy("command_center").activity, "MEDIUM_ACTIVITY");
    assert.equal(resolveAdminRefreshIntervalMs("command_center", false), 30_000);
  });
});
