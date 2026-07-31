import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  canManageFeatureConfig,
  canViewFeatureConfig,
} from "@/lib/api/admin-feature-config";

describe("admin feature config helpers", () => {
  it("gates view and manage with features permissions", () => {
    assert.equal(canViewFeatureConfig(undefined), true);
    assert.equal(canViewFeatureConfig(["features.view"]), true);
    assert.equal(canViewFeatureConfig(["settings.view"]), false);
    assert.equal(canManageFeatureConfig(["features.manage"]), true);
    assert.equal(canManageFeatureConfig(["features.view"]), false);
  });

  it("exposes Settings → Features nav item behind features.view", () => {
    const nav = adminSettingsNavItems.find(
      (item) => item.href === "/admin/settings/features",
    );
    assert.ok(nav);
    assert.equal(nav?.permission, "features.view");
    assert.equal(hasAdminPermission(["features.view"], nav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["settings.view"], nav?.permission ?? ""), false);
  });
});
