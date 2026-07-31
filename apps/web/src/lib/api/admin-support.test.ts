import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  canAssignSupport,
  canManageSupport,
  canViewSupport,
} from "./admin-support.ts";
import { hasAdminPermission } from "./admin-me.ts";

describe("admin support helpers", () => {
  it("checks support permissions", () => {
    assert.equal(canViewSupport(["support.view"]), true);
    assert.equal(canManageSupport(["support.manage"]), true);
    assert.equal(canAssignSupport(["support.assign"]), true);
    assert.equal(canViewSupport(["orders.view"]), false);
    assert.equal(hasAdminPermission(["support.view"], "support.view"), true);
  });
});
