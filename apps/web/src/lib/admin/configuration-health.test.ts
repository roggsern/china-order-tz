import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import {
  canViewConfigurationHealth,
  configurationHealthScoreTone,
  mapConfigurationHealthPayload,
} from "@/lib/admin/configuration-health";
import { hasAdminPermission } from "@/lib/api/admin-me";

describe("configuration health mapping", () => {
  it("gates visibility with settings.view", () => {
    assert.equal(canViewConfigurationHealth(undefined), true);
    assert.equal(canViewConfigurationHealth(["settings.view"]), true);
    assert.equal(canViewConfigurationHealth(["features.view"]), false);
  });

  it("maps payload into score, groups, warnings, and critical issues", () => {
    const view = mapConfigurationHealthPayload({
      overall_score: 70,
      status: "warning",
      checks: [
        {
          group: "payments",
          status: "healthy",
          message: "Payment configuration looks healthy (default: nmb).",
          severity: "info",
        },
        {
          group: "shipping",
          status: "warning",
          message: "Shipping method [air_freight] is inactive.",
          severity: "warning",
        },
        {
          group: "notifications",
          status: "critical",
          message: "In-app notifications are disabled; customers may miss order updates.",
          severity: "critical",
        },
      ],
      summary: {
        critical_count: 1,
        warning_count: 1,
        info_count: 0,
        healthy_count: 1,
      },
    });

    assert.equal(view.overallScore, 70);
    assert.equal(view.status, "warning");
    assert.equal(view.criticalIssues.length, 1);
    assert.equal(view.warnings.length, 1);
    assert.equal(view.groups.length, 3);
    assert.equal(view.groups[0]?.status, "critical");
    assert.equal(configurationHealthScoreTone(90), "text-emerald-300");
    assert.equal(configurationHealthScoreTone(70), "text-amber-300");
    assert.equal(configurationHealthScoreTone(40), "text-red-300");
  });

  it("exposes Settings → Config Health nav item behind settings.view", () => {
    const nav = adminSettingsNavItems.find((item) => item.href === "/admin/settings/health");
    assert.ok(nav);
    assert.equal(nav?.permission, "settings.view");
    assert.equal(hasAdminPermission(["settings.view"], nav?.permission ?? ""), true);
  });
});
