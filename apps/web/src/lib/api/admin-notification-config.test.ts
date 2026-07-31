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

  it("exposes Settings → Notifications nav item behind notifications.view", () => {
    const nav = adminSettingsNavItems.find(
      (item) => item.href === "/admin/settings/notifications",
    );
    assert.ok(nav);
    assert.equal(nav?.permission, "notifications.view");
    assert.equal(hasAdminPermission(["notifications.view"], nav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["settings.view"], nav?.permission ?? ""), false);
  });
});
