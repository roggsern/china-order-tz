import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  canManageStoreSettings,
  canViewStoreSettings,
} from "@/lib/api/admin-store-settings";

describe("admin store settings helpers", () => {
  it("gates view and manage with stores permissions", () => {
    assert.equal(canViewStoreSettings(undefined), true);
    assert.equal(canViewStoreSettings(["stores.view"]), true);
    assert.equal(canViewStoreSettings(["settings.view"]), false);
    assert.equal(canManageStoreSettings(["stores.manage"]), true);
    assert.equal(canManageStoreSettings(["stores.view"]), false);
  });

  it("exposes Settings → Store nav item behind stores.view", () => {
    const nav = adminSettingsNavItems.find((item) => item.href === "/admin/settings/store");
    assert.ok(nav);
    assert.equal(nav?.permission, "stores.view");
    assert.equal(hasAdminPermission(["stores.view"], nav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["features.view"], nav?.permission ?? ""), false);
  });
});
