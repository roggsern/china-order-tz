import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER,
  filterAccessibleSections,
  resolveSectionOrder,
  resolveVisibleGroups,
} from "@/lib/admin/admin-dashboard-sections";
import {
  normalizeAdminDashboardPreferences,
  toggleDashboardSectionCollapsed,
  toggleDashboardSectionHidden,
} from "@/lib/admin/admin-dashboard-preferences";

describe("admin-dashboard-preferences", () => {
  it("normalizes invalid stored preferences", () => {
    const prefs = normalizeAdminDashboardPreferences({
      sectionOrder: ["attention_required", "invalid-section" as never],
      collapsedSections: ["business_overview"],
      hiddenSections: ["quick_exports", "not-real" as never],
    });

    assert.deepEqual(prefs.sectionOrder, ["attention_required"]);
    assert.deepEqual(prefs.collapsedSections, ["business_overview"]);
    assert.deepEqual(prefs.hiddenSections, ["quick_exports"]);
  });

  it("toggles collapsed and hidden sections", () => {
    const base = normalizeAdminDashboardPreferences({});
    const collapsed = toggleDashboardSectionCollapsed(base, "operations_traffic");
    assert.deepEqual(collapsed.collapsedSections, ["operations_traffic"]);

    const hidden = toggleDashboardSectionHidden(base, "growth_intelligence");
    assert.deepEqual(hidden.hiddenSections, ["growth_intelligence"]);
  });
});

describe("admin-dashboard-sections", () => {
  it("defaults to the five-group section order", () => {
    assert.equal(DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER[0], "attention_required");
    assert.equal(DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER[1], "business_overview");
    assert.ok(DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER.includes("growth_intelligence"));
    assert.ok(DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER.includes("quick_exports"));
  });

  it("respects hidden sections and permissions when resolving order", () => {
    const order = resolveSectionOrder(
      ["financial_summary", "attention_required"],
      ["reports.view"],
      ["quick_exports"],
    );

    assert.deepEqual(order.slice(0, 2), ["financial_summary", "attention_required"]);
    assert.equal(order.includes("quick_exports"), false);
  });

  it("filters inaccessible sections when permissions are missing", () => {
    const accessible = filterAccessibleSections([], ["attention_required", "business_overview"]);
    assert.equal(accessible.length, 0);
  });

  it("builds visible groups after hiding sections", () => {
    const groups = resolveVisibleGroups(["reports.view"], ["storefront_traffic"]);
    const growthGroup = groups.find((group) => group.key === "growth_intelligence");

    assert.ok(growthGroup);
    assert.equal(growthGroup?.sections.includes("storefront_traffic"), false);
    assert.equal(growthGroup?.sections.includes("growth_intelligence"), true);
  });
});
