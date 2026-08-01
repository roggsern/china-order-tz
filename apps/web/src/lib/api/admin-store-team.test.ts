import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  canManageStoreTeam,
  canViewStoreTeam,
  scopeLabel,
} from "./admin-store-team";
import { hasAdminPermission } from "./admin-me";
import { canViewStoreDashboard, formatStoreMoney } from "./admin-store-dashboard";

describe("admin store team helpers", () => {
  it("checks team permissions", () => {
    assert.equal(canViewStoreTeam(["stores.team.view"]), true);
    assert.equal(canViewStoreTeam(["stores.view"]), false);
    assert.equal(canManageStoreTeam(["stores.team.manage"]), true);
    assert.equal(canManageStoreTeam(["stores.assign"]), true);
    assert.equal(canManageStoreTeam(["stores.team.view"]), false);
    assert.equal(hasAdminPermission(["stores.assign"], "stores.assign"), true);
  });

  it("labels operational scopes", () => {
    assert.equal(scopeLabel("store_manager"), "Store Manager");
    assert.equal(scopeLabel("store_viewer"), "Store Viewer");
  });
});

describe("admin store dashboard helpers", () => {
  it("checks dashboard permission and formats money", () => {
    assert.equal(canViewStoreDashboard(["stores.view"]), true);
    assert.equal(canViewStoreDashboard([]), false);
    assert.match(formatStoreMoney(1000), /1,?000/);
  });
});
