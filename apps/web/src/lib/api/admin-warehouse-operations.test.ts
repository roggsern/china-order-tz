import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  canManageWarehouse,
  canTransferWarehouse,
  canViewWarehouse,
} from "@/lib/api/admin-warehouse-operations";

describe("admin warehouse operations helpers", () => {
  it("gates warehouse view, manage, and transfer permissions", () => {
    assert.equal(canViewWarehouse(["warehouse.view"]), true);
    assert.equal(canViewWarehouse(["warehouse.jobs.view"]), true);
    assert.equal(canViewWarehouse(["inventory.view"]), false);

    assert.equal(canManageWarehouse(["warehouse.manage"]), true);
    assert.equal(canManageWarehouse(["warehouse.jobs.update"]), true);
    assert.equal(canManageWarehouse(["warehouse.view"]), false);

    assert.equal(canTransferWarehouse(["warehouse.transfer"]), true);
    assert.equal(canTransferWarehouse(["inventory.transfer"]), true);
    assert.equal(canTransferWarehouse(["warehouse.manage"]), false);
  });

  it("exposes Warehouse nav item behind warehouse.view", () => {
    const nav = adminNavItems.find((item) => item.href === "/admin/warehouse");
    assert.ok(nav);
    assert.equal(nav?.permission, "warehouse.view");
    assert.equal(hasAdminPermission(["warehouse.view"], nav?.permission ?? ""), true);
  });
});
