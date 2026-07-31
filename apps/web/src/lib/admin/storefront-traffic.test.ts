import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { AdminStorefrontTrafficMetrics } from "@/lib/api/admin-reporting";
import {
  formatStorefrontGrowthPercent,
  hasStorefrontTrafficData,
  storefrontGrowthAccent,
  storefrontTrafficEmptyMessage,
} from "@/lib/admin/storefront-traffic";
import {
  getAdminRefreshPolicy,
  resolveAdminRefreshIntervalMs,
} from "@/lib/admin/admin-refresh-policy";

function emptyMetrics(overrides?: Partial<AdminStorefrontTrafficMetrics>): AdminStorefrontTrafficMetrics {
  return {
    reference_date: "2026-07-23",
    visitors_today: 0,
    sessions_today: 0,
    new_visitors: 0,
    returning_visitors: 0,
    growth: {
      visitors_change: 0,
      visitors_change_percent: 0,
      sessions_change: 0,
      sessions_change_percent: 0,
    },
    top_pages: [],
    top_products: [],
    top_searches: [],
    ...overrides,
  };
}

describe("storefront-traffic helpers", () => {
  it("formats growth percentages with sign and one decimal", () => {
    assert.equal(formatStorefrontGrowthPercent(0), "0%");
    assert.equal(formatStorefrontGrowthPercent(12.34), "+12.3%");
    assert.equal(formatStorefrontGrowthPercent(-5.67), "-5.7%");
  });

  it("maps growth accent colors", () => {
    assert.equal(storefrontGrowthAccent(1), "text-emerald-600");
    assert.equal(storefrontGrowthAccent(-1), "text-red-600");
    assert.equal(storefrontGrowthAccent(0), "text-zinc-500");
  });

  it("detects empty storefront traffic payloads", () => {
    assert.equal(hasStorefrontTrafficData(emptyMetrics()), false);
    assert.equal(
      hasStorefrontTrafficData(emptyMetrics({ visitors_today: 1 })),
      true,
    );
    assert.equal(
      hasStorefrontTrafficData(emptyMetrics({ top_pages: [{ path: "/", views: 2 }] })),
      true,
    );
  });

  it("returns empty-state copy when no traffic exists", () => {
    assert.equal(
      storefrontTrafficEmptyMessage(emptyMetrics()),
      "No storefront traffic recorded for this period yet.",
    );
    assert.equal(storefrontTrafficEmptyMessage(emptyMetrics({ sessions_today: 3 })), "");
  });
});

describe("storefront traffic dashboard refresh", () => {
  it("uses MEDIUM_ACTIVITY for command center polling", () => {
    assert.equal(getAdminRefreshPolicy("command_center").activity, "MEDIUM_ACTIVITY");
    assert.equal(resolveAdminRefreshIntervalMs("command_center", false), 30_000);
    assert.equal(resolveAdminRefreshIntervalMs("command_center", true), 60_000);
  });
});
