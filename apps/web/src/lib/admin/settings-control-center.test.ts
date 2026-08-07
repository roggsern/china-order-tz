import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import {
  canViewSettingsControlCenter,
  mapSettingsAuditChange,
  mapSettingsDashboardPayload,
} from "@/lib/admin/settings-control-center";
import { hasAdminPermission } from "@/lib/api/admin-me";

describe("settings control center mapping", () => {
  it("gates visibility with settings.view", () => {
    assert.equal(canViewSettingsControlCenter(undefined), true);
    assert.equal(canViewSettingsControlCenter(["settings.view"]), true);
    assert.equal(canViewSettingsControlCenter(["features.view"]), false);
  });

  it("maps dashboard payload into health, modules, actions, and recent changes", () => {
    const view = mapSettingsDashboardPayload(
      {
        health_score: 82,
        status: "warning",
        summary: {
          critical_count: 0,
          warning_count: 1,
          info_count: 0,
          healthy_count: 5,
        },
        module_statuses: [
          {
            key: "payments",
            label: "Payments",
            href: "/admin/settings/payments",
            permission: "payments.config.view",
            status: "healthy",
            message: "Payment configuration looks healthy.",
            check_count: 1,
          },
          {
            key: "shipping",
            label: "Shipping",
            href: "/admin/settings/shipping",
            permission: "shipping.view",
            status: "warning",
            message: "Shipping method inactive.",
            check_count: 2,
          },
          {
            key: "features",
            label: "Features",
            href: "/admin/settings/features",
            permission: "features.view",
            status: "healthy",
            message: "Feature configuration looks healthy.",
            check_count: 1,
          },
        ],
        quick_actions: [
          {
            key: "config_health",
            label: "View configuration health",
            href: "/admin/settings/health",
            permission: "settings.view",
          },
          {
            key: "payments",
            label: "Manage payment toggles",
            href: "/admin/settings/payments",
            permission: "payments.config.view",
          },
        ],
        recent_changes: [
          {
            id: "log-1",
            actor: { id: "admin-1", name: "Ada", type: "admin" },
            event: "payment_configuration_updated",
            event_label: "Payment Configuration Updated",
            before: { default_provider: "nmb", api_key: "[REDACTED]" },
            after: { default_provider: "mpesa", api_key: "[REDACTED]" },
            timestamp: "2026-07-29T01:00:00+00:00",
            description: "Updated payment toggles",
          },
        ],
      },
      ["settings.view", "shipping.view"],
    );

    assert.equal(view.healthScore, 82);
    assert.equal(view.status, "warning");
    assert.equal(view.summary.warningCount, 1);
    assert.equal(view.modules.length, 1);
    assert.equal(view.modules[0]?.key, "shipping");
    assert.equal(view.quickActions.length, 1);
    assert.equal(view.quickActions[0]?.key, "config_health");
    assert.equal(view.recentChanges.length, 1);
    assert.equal(view.recentChanges[0]?.actorName, "Ada");
    assert.equal(view.recentChanges[0]?.eventLabel, "Payment Configuration Updated");
    assert.equal(view.recentChanges[0]?.after?.api_key, "[REDACTED]");
  });

  it("maps audit history rows for before/after display", () => {
    const row = mapSettingsAuditChange({
      id: "log-2",
      actor: { id: null, name: null, type: "system" },
      event: "settings_updated",
      before: { key: "features.maintenance_mode", value: false },
      after: { key: "features.maintenance_mode", value: true },
      timestamp: null,
      description: null,
    });

    assert.equal(row.actorName, "system");
    assert.equal(row.event, "settings_updated");
    assert.equal(row.before?.value, false);
    assert.equal(row.after?.value, true);
  });

  it("exposes Overview and History nav items behind settings.view", () => {
    const overview = adminSettingsNavItems.find((item) => item.href === "/admin/settings");
    const history = adminSettingsNavItems.find((item) => item.href === "/admin/settings/history");

    assert.ok(overview);
    assert.equal(overview?.exact, true);
    assert.equal(overview?.permission, "settings.view");
    assert.ok(history);
    assert.equal(history?.permission, "settings.view");
    assert.equal(hasAdminPermission(["settings.view"], overview?.permission ?? ""), true);
  });

  it("limits Settings sidebar to Overview, History, Users, Roles, and Permissions", () => {
    assert.deepEqual(
      adminSettingsNavItems.map((item) => item.label),
      ["Overview", "History", "Users", "Roles", "Permissions"],
    );
  });
});
