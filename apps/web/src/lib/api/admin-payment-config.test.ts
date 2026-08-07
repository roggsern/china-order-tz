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

  it("keeps Payments settings permission-gated outside the Settings sidebar", () => {
    assert.equal(
      adminSettingsNavItems.some((item) => item.href === "/admin/settings/payments"),
      false,
    );
    assert.equal(canViewPaymentConfig(["payments.config.view"]), true);
    assert.equal(hasAdminPermission(["payments.config.view"], "payments.config.view"), true);
    assert.equal(hasAdminPermission(["payments.view"], "payments.config.view"), false);
  });
});
