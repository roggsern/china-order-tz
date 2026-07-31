import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  canApproveAdminRefunds,
  canManageAdminRefunds,
  canViewAdminRefunds,
  refundStatusBadgeClass,
  refundStatusLabel,
} from "@/lib/api/admin-refunds";

describe("admin refunds helpers", () => {
  it("gates view, manage, and approve permissions", () => {
    assert.equal(canViewAdminRefunds(undefined), true);
    assert.equal(canViewAdminRefunds(["refunds.view"]), true);
    assert.equal(canViewAdminRefunds(["refunds.manage"]), false);

    assert.equal(canManageAdminRefunds(["refunds.manage"]), true);
    assert.equal(canManageAdminRefunds(["refunds.view"]), false);

    assert.equal(canApproveAdminRefunds(["refunds.approve"]), true);
    assert.equal(canApproveAdminRefunds(["refunds.manage"]), false);
  });

  it("maps refund status labels and badge classes", () => {
    assert.equal(refundStatusLabel("requested"), "Requested");
    assert.equal(refundStatusLabel("under_review"), "Under review");
    assert.equal(refundStatusLabel("completed"), "Completed");
    assert.equal(refundStatusLabel("unknown_status"), "unknown_status");

    assert.match(refundStatusBadgeClass("completed"), /emerald/);
    assert.match(refundStatusBadgeClass("failed"), /red/);
    assert.match(refundStatusBadgeClass("approved"), /sky/);
    assert.match(refundStatusBadgeClass("requested"), /amber/);
  });

  it("exposes Refunds nav item behind refunds.view", () => {
    const nav = adminNavItems.find((item) => item.href === "/admin/refunds");
    assert.ok(nav);
    assert.equal(nav?.permission, "refunds.view");
    assert.equal(hasAdminPermission(["refunds.view"], nav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["orders.view"], nav?.permission ?? ""), false);
  });
});
