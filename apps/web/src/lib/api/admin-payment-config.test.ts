import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  canManagePaymentConfig,
  canViewPaymentConfig,
} from "@/lib/api/admin-payment-config";

describe("admin payment config helpers", () => {
  it("gates view and manage with payments.config permissions", () => {
    assert.equal(canViewPaymentConfig(undefined), true);
    assert.equal(canViewPaymentConfig(["payments.config.view"]), true);
    assert.equal(canViewPaymentConfig(["payments.view"]), false);
    assert.equal(canManagePaymentConfig(["payments.config.manage"]), true);
    assert.equal(canManagePaymentConfig(["payments.config.view"]), false);
  });

  it("exposes Settings → Payments nav item behind payments.config.view", () => {
    const nav = adminSettingsNavItems.find(
      (item) => item.href === "/admin/settings/payments",
    );
    assert.ok(nav);
    assert.equal(nav?.permission, "payments.config.view");
    assert.equal(hasAdminPermission(["payments.config.view"], nav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["payments.view"], nav?.permission ?? ""), false);
  });
});
