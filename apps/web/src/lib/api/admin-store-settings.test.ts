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

  it("keeps Store settings permission-gated outside the Settings sidebar", () => {
    assert.equal(
      adminSettingsNavItems.some((item) => item.href === "/admin/settings/store"),
      false,
    );
    assert.equal(canViewStoreSettings(["stores.view"]), true);
    assert.equal(hasAdminPermission(["stores.view"], "stores.view"), true);
    assert.equal(hasAdminPermission(["features.view"], "stores.view"), false);
  });
});
