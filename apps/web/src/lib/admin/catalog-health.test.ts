import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  canViewCatalogHealth,
  catalogHealthEmptyMessage,
  catalogHealthSeverityBadgeClass,
  groupCatalogHealthIssues,
  isCatalogHealthEmpty,
  mapCatalogHealthPayload,
} from "@/lib/admin/catalog-health";
import type { CatalogHealthPayload } from "@/lib/api/admin-catalog-health";
import {
  getAdminRefreshPolicy,
  isAdminAutoRefreshEnabled,
  resolveAdminRefreshIntervalMs,
} from "@/lib/admin/admin-refresh-policy";

function samplePayload(overrides?: Partial<CatalogHealthPayload>): CatalogHealthPayload {
  const empty = {
    severity: "info" as const,
    priority: "P2" as const,
    count: 0,
    product_ids: [] as string[],
    variant_ids: [] as string[],
  };

  return {
    summary: {
      health_score: 100,
      critical_count: 0,
      warning_count: 0,
      ...overrides?.summary,
    },
    issues: {
      commerce_readiness: {
        active_not_purchasable: { ...empty },
        missing_valid_price: { ...empty },
        ...overrides?.issues?.commerce_readiness,
      },
      media: {
        active_public_without_images: { ...empty },
        variants_without_media: { ...empty },
        ...overrides?.issues?.media,
      },
      inventory: {
        active_missing_inventory_policy: { ...empty },
        ...overrides?.issues?.inventory,
      },
      catalog_quality: {
        variants_without_sku: { ...empty },
        variants_without_barcode: { ...empty },
        missing_descriptions: { ...empty },
        ...overrides?.issues?.catalog_quality,
      },
      ...overrides?.issues,
    },
  };
}

describe("catalog-health mapping", () => {
  it("maps inventory group copy for TZ_LOCAL inventory policy scope", () => {
    const report = mapCatalogHealthPayload(samplePayload());
    const inventoryGroup = report.groups.find((group) => group.id === "inventory");

    assert.equal(
      inventoryGroup?.description,
      "Missing inventory policy on active TZ_LOCAL products and variants.",
    );
  });

  it("maps API response into summary and grouped metrics", () => {
    const report = mapCatalogHealthPayload(
      samplePayload({
        summary: { health_score: 70, critical_count: 2, warning_count: 1 },
        issues: {
          commerce_readiness: {
            active_not_purchasable: {
              severity: "critical",
              priority: "P0",
              count: 1,
              product_ids: ["prod-1"],
            },
            missing_valid_price: {
              severity: "critical",
              priority: "P0",
              count: 1,
              product_ids: ["prod-2"],
            },
          },
          media: {
            active_public_without_images: {
              severity: "critical",
              priority: "P0",
              count: 0,
              product_ids: [],
            },
            variants_without_media: {
              severity: "warning",
              priority: "P1",
              count: 1,
              variant_ids: ["var-1"],
            },
          },
          inventory: {
            active_missing_inventory_policy: {
              severity: "warning",
              priority: "P1",
              count: 0,
              product_ids: [],
            },
          },
          catalog_quality: {
            variants_without_sku: {
              severity: "warning",
              priority: "P1",
              count: 0,
              variant_ids: [],
            },
            variants_without_barcode: {
              severity: "warning",
              priority: "P1",
              count: 0,
              variant_ids: [],
            },
            missing_descriptions: {
              severity: "info",
              priority: "P2",
              count: 2,
              product_ids: ["prod-3", "prod-4"],
            },
          },
        },
      }),
    );

    assert.equal(report.summary.health_score, 70);
    assert.equal(report.summary.critical_count, 2);
    assert.equal(report.groups.length, 4);
    assert.equal(report.groups[0]?.title, "Commerce readiness");
    assert.equal(report.groups[0]?.metrics[0]?.label, "Missing prices");
    assert.equal(
      report.groups[0]?.metrics.find((metric) => metric.key === "active_not_purchasable")?.label,
      "Not purchasable",
    );
    assert.equal(report.groups[1]?.metrics[1]?.label, "Variant media gaps");
    assert.equal(report.groups[2]?.metrics[0]?.label, "Inventory issues");
    assert.equal(report.isEmpty, false);
  });

  it("groups only metrics with counts for issue cards", () => {
    const report = mapCatalogHealthPayload(
      samplePayload({
        issues: {
          commerce_readiness: {
            active_not_purchasable: {
              severity: "critical",
              priority: "P0",
              count: 1,
              product_ids: ["a"],
            },
            missing_valid_price: {
              severity: "critical",
              priority: "P0",
              count: 0,
              product_ids: [],
            },
          },
          media: {
            active_public_without_images: {
              severity: "critical",
              priority: "P0",
              count: 0,
              product_ids: [],
            },
            variants_without_media: {
              severity: "warning",
              priority: "P1",
              count: 0,
              variant_ids: [],
            },
          },
          inventory: {
            active_missing_inventory_policy: {
              severity: "warning",
              priority: "P1",
              count: 0,
              product_ids: [],
            },
          },
          catalog_quality: {
            variants_without_sku: {
              severity: "warning",
              priority: "P1",
              count: 0,
              variant_ids: [],
            },
            variants_without_barcode: {
              severity: "warning",
              priority: "P1",
              count: 0,
              variant_ids: [],
            },
            missing_descriptions: {
              severity: "info",
              priority: "P2",
              count: 0,
              product_ids: [],
            },
          },
        },
      }),
    );

    const grouped = groupCatalogHealthIssues(report.groups).filter(
      (group) => group.metrics.length > 0,
    );

    assert.equal(grouped.length, 1);
    assert.equal(grouped[0]?.id, "commerce_readiness");
    assert.equal(grouped[0]?.metrics.length, 1);
    assert.equal(grouped[0]?.metrics[0]?.key, "active_not_purchasable");
  });

  it("detects empty state and exposes empty copy", () => {
    const report = mapCatalogHealthPayload(samplePayload());
    assert.equal(report.isEmpty, true);
    assert.equal(isCatalogHealthEmpty(report.groups), true);
    assert.match(catalogHealthEmptyMessage(), /healthy/i);
  });

  it("gates visibility with catalog.view permission", () => {
    assert.equal(canViewCatalogHealth(undefined), true);
    assert.equal(canViewCatalogHealth(["catalog.view"]), true);
    assert.equal(canViewCatalogHealth(["orders.view"]), false);
  });

  it("maps severity badge classes", () => {
    assert.match(catalogHealthSeverityBadgeClass("critical"), /red/);
    assert.match(catalogHealthSeverityBadgeClass("warning"), /amber/);
    assert.match(catalogHealthSeverityBadgeClass("info"), /sky/);
  });
});

describe("catalog-health refresh policy", () => {
  it("reuses MEDIUM_ACTIVITY admin refresh policy", () => {
    assert.equal(getAdminRefreshPolicy("catalog_health").activity, "MEDIUM_ACTIVITY");
    assert.equal(resolveAdminRefreshIntervalMs("catalog_health", false), 30_000);
    assert.equal(isAdminAutoRefreshEnabled("catalog_health"), true);
  });
});
