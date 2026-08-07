import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  canManageNotificationConfig,
  canViewNotificationConfig,
} from "@/lib/api/admin-notification-config";

describe("admin notification config helpers", () => {
  it("gates view and manage with notifications permissions", () => {
    assert.equal(canViewNotificationConfig(undefined), true);
    assert.equal(canViewNotificationConfig(["notifications.view"]), true);
    assert.equal(canViewNotificationConfig(["notifications.templates.view"]), false);
    assert.equal(canManageNotificationConfig(["notifications.manage"]), true);
    assert.equal(canManageNotificationConfig(["notifications.view"]), false);
  });

  it("keeps Notifications settings permission-gated outside the Settings sidebar", () => {
    assert.equal(
      adminSettingsNavItems.some((item) => item.href === "/admin/settings/notifications"),
      false,
    );
    assert.equal(canViewNotificationConfig(["notifications.view"]), true);
    assert.equal(hasAdminPermission(["notifications.view"], "notifications.view"), true);
    assert.equal(hasAdminPermission(["settings.view"], "notifications.view"), false);
  });
});
