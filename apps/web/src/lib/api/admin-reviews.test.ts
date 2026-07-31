import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  canManageAdminReviews,
  canViewAdminReviews,
  reviewStatusBadgeClass,
  reviewStatusLabel,
} from "@/lib/api/admin-reviews";

describe("admin reviews helpers", () => {
  it("gates view and manage permissions", () => {
    assert.equal(canViewAdminReviews(undefined), true);
    assert.equal(canViewAdminReviews(["reviews.view"]), true);
    assert.equal(canViewAdminReviews(["reviews.manage"]), false);

    assert.equal(canManageAdminReviews(["reviews.manage"]), true);
    assert.equal(canManageAdminReviews(["reviews.view"]), false);
  });

  it("maps review status labels and badge classes", () => {
    assert.equal(reviewStatusLabel("pending"), "Pending");
    assert.equal(reviewStatusLabel("approved"), "Approved");
    assert.equal(reviewStatusLabel("rejected"), "Rejected");

    assert.match(reviewStatusBadgeClass("approved"), /emerald/);
    assert.match(reviewStatusBadgeClass("rejected"), /red/);
    assert.match(reviewStatusBadgeClass("pending"), /amber/);
  });

  it("exposes Reviews nav item behind reviews.view", () => {
    const nav = adminNavItems.find((item) => item.href === "/admin/reviews");
    assert.ok(nav);
    assert.equal(nav?.permission, "reviews.view");
    assert.equal(hasAdminPermission(["reviews.view"], nav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["orders.view"], nav?.permission ?? ""), false);
  });
});
