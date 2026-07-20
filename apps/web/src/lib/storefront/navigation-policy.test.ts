import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resolveActiveOrdersBadgeCount } from "../api/customer-dashboard";
import {
  commerceSourceLabel,
  getMobileDrawerItems,
  getPrimaryNavItems,
  isNavItemActive,
  normalizeCommerceSource,
  resolveActiveJourney,
  resolveStorefrontNavAudience,
  shouldShowGuestAuthActions,
  shouldShowMyOrders,
  shouldShowNotifications,
  STOREFRONT_NAV_LABELS,
} from "./navigation-policy";

describe("storefront navigation policy — labels", () => {
  it("uses exact title-style journey labels", () => {
    assert.equal(STOREFRONT_NAV_LABELS.orderFromChina, "Order from China");
    assert.equal(STOREFRONT_NAV_LABELS.buyFromTz, "Buy from TZ");
    assert.equal(STOREFRONT_NAV_LABELS.myOrders, "My Orders");
    assert.equal(STOREFRONT_NAV_LABELS.aboutUs, "About Us");
    assert.equal(STOREFRONT_NAV_LABELS.contactUs, "Contact Us");
  });

  it("keeps lowercase from and uppercase TZ", () => {
    assert.match(STOREFRONT_NAV_LABELS.orderFromChina, / from /);
    assert.match(STOREFRONT_NAV_LABELS.buyFromTz, / from TZ$/);
    assert.doesNotMatch(STOREFRONT_NAV_LABELS.orderFromChina, /ORDER FROM CHINA/);
    assert.doesNotMatch(STOREFRONT_NAV_LABELS.buyFromTz, /BUY FROM TZ/);
    assert.doesNotMatch(STOREFRONT_NAV_LABELS.buyFromTz, /Buy From TZ/);
  });
});

describe("storefront navigation policy — guest vs customer", () => {
  const guest = resolveStorefrontNavAudience({ isLoggedIn: false });
  const customer = resolveStorefrontNavAudience({ isLoggedIn: true });

  it("hides My Orders from guests", () => {
    assert.equal(shouldShowMyOrders(guest), false);
    const labels = getPrimaryNavItems(guest).map((item) => item.label);
    assert.deepEqual(labels, [
      "Order from China",
      "Buy from TZ",
      "About Us",
      "Contact Us",
    ]);
    assert.equal(labels.includes("My Orders"), false);
    assert.equal(labels.includes("Track Order"), false);
  });

  it("shows My Orders for authenticated customers", () => {
    assert.equal(shouldShowMyOrders(customer), true);
    const labels = getPrimaryNavItems(customer).map((item) => item.label);
    assert.ok(labels.includes("My Orders"));
    assert.ok(labels.includes("Order from China"));
    assert.ok(labels.includes("Buy from TZ"));
  });

  it("guest mobile drawer includes Sign In / Create Account and excludes My Orders", () => {
    const ids = getMobileDrawerItems(guest).map((item) => item.id);
    assert.ok(ids.includes("signIn"));
    assert.ok(ids.includes("createAccount"));
    assert.equal(ids.includes("myOrders"), false);
    assert.equal(ids.includes("notifications"), false);
    assert.equal(ids.includes("myAccount"), false);
    assert.equal(shouldShowGuestAuthActions(guest), true);
    assert.equal(shouldShowNotifications(guest), false);
  });

  it("customer mobile drawer includes My Orders, My Account, Sign Out — not Sign In", () => {
    const items = getMobileDrawerItems(customer);
    const ids = items.map((item) => item.id);
    const labels = items.map((item) => item.label);
    assert.ok(ids.includes("myOrders"));
    assert.ok(ids.includes("myAccount"));
    assert.ok(ids.includes("signOut"));
    assert.ok(ids.includes("notifications"));
    assert.equal(ids.includes("signIn"), false);
    assert.equal(ids.includes("createAccount"), false);
    assert.ok(labels.includes("My Orders"));
    assert.ok(labels.includes("My Account"));
    assert.equal(shouldShowNotifications(customer), true);
  });
});

describe("storefront navigation policy — active journey", () => {
  it("activates Order from China on china catalog routes", () => {
    assert.equal(resolveActiveJourney("/products", "origin=china"), "china");
    assert.equal(resolveActiveJourney("/categories/phones"), "china");
    assert.equal(isNavItemActive("orderFromChina", "china"), true);
  });

  it("activates Buy from TZ on local store routes", () => {
    assert.equal(resolveActiveJourney("/buy-from-tz"), "tz");
    assert.equal(resolveActiveJourney("/buy-from-tz/zion-mode"), "tz");
    assert.equal(resolveActiveJourney("/products", "origin=tz"), "tz");
    assert.equal(isNavItemActive("buyFromTz", "tz"), true);
  });

  it("activates My Orders on order routes", () => {
    assert.equal(resolveActiveJourney("/orders"), "orders");
    assert.equal(resolveActiveJourney("/orders/COT-1"), "orders");
    assert.equal(resolveActiveJourney("/track/abc"), "orders");
    assert.equal(isNavItemActive("myOrders", "orders"), true);
  });
});

describe("storefront navigation policy — commerce source labels", () => {
  it("normalizes China/Dar API sources", () => {
    assert.equal(normalizeCommerceSource("China"), "china");
    assert.equal(normalizeCommerceSource("Dar"), "tz");
    assert.equal(normalizeCommerceSource("china"), "china");
    assert.equal(normalizeCommerceSource("local"), "tz");
    assert.equal(commerceSourceLabel("China")?.short, "China Import");
    assert.equal(commerceSourceLabel("Dar")?.short, "Tanzania Store");
  });
});

describe("active orders badge", () => {
  it("sums active + in-transit and ignores completed", () => {
    assert.equal(
      resolveActiveOrdersBadgeCount({
        activeOrders: 2,
        inTransitOrders: 1,
        pendingPayments: 4,
        completedOrders: 9,
      }),
      3,
    );
  });
});
