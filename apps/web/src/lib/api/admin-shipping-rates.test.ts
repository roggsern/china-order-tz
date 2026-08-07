import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  canManageShippingRates,
  canViewShippingRates,
} from "@/lib/api/admin-shipping-rates";

describe("admin shipping rates helpers", () => {
  it("gates view and manage with shipping permissions", () => {
    assert.equal(canViewShippingRates(undefined), true);
    assert.equal(canViewShippingRates(["shipping.view"]), true);
    assert.equal(canViewShippingRates(["orders.view"]), false);
    assert.equal(canManageShippingRates(["shipping.manage"]), true);
    assert.equal(canManageShippingRates(["shipping.view"]), false);
  });

  it("keeps Shipping settings permission-gated outside the Settings sidebar", () => {
    assert.equal(
      adminSettingsNavItems.some((item) => item.href === "/admin/settings/shipping"),
      false,
    );
    assert.equal(canViewShippingRates(["shipping.view"]), true);
    assert.equal(hasAdminPermission(["shipping.view"], "shipping.view"), true);
    assert.equal(hasAdminPermission(["settings.view"], "shipping.view"), false);
  });
});
