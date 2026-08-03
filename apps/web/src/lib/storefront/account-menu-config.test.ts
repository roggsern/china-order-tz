import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  GUEST_ACCOUNT_MENU_ITEMS,
  MOBILE_HEADER_ACCOUNT_MENU_ITEMS,
  resolveAuthenticatedAccountMenuItems,
  resolveGuestAccountMenuItems,
} from "./account-menu-config";
import { resolveAccountMenuPresentation } from "./account-menu-presentation";
import { resolveStorefrontNavAudience } from "./navigation-policy";

describe("account menu config — guest menu", () => {
  it("renders Sign In and Create Account links", () => {
    const items = resolveGuestAccountMenuItems();

    assert.deepEqual(
      items.map((item) => item.label),
      ["Sign In", "Create Account"],
    );
    assert.deepEqual(
      items.map((item) => item.href),
      ["/login", "/register"],
    );
  });

  it("uses shared guest menu constants", () => {
    assert.equal(GUEST_ACCOUNT_MENU_ITEMS.length, 2);
    assert.equal(GUEST_ACCOUNT_MENU_ITEMS[0]?.href, "/login");
    assert.equal(GUEST_ACCOUNT_MENU_ITEMS[1]?.href, "/register");
  });
});

describe("account menu config — authenticated mobile header menu", () => {
  it("renders My Account, My Orders, Profile, and Sign Out", () => {
    const items = resolveAuthenticatedAccountMenuItems({
      preset: "header",
      wishlistEnabled: false,
      featuresReady: true,
    });

    assert.deepEqual(
      items.map((item) => item.label),
      ["My Account", "My Orders", "Profile", "Sign Out"],
    );
  });

  it("uses account and orders routes with profile security page", () => {
    const links = MOBILE_HEADER_ACCOUNT_MENU_ITEMS.filter((item) => item.type === "link");

    assert.deepEqual(
      links.map((item) => item.href),
      ["/account", "/orders", "/account/security"],
    );
  });

  it("includes logout action for mobile header preset", () => {
    const logout = MOBILE_HEADER_ACCOUNT_MENU_ITEMS.find((item) => item.type === "logout");
    assert.equal(logout?.label, "Sign Out");
  });
});

describe("account menu config — full desktop menu", () => {
  it("hides wishlist when feature is disabled", () => {
    const items = resolveAuthenticatedAccountMenuItems({
      preset: "full",
      wishlistEnabled: false,
      featuresReady: true,
    });

    assert.equal(
      items.some((item) => item.type === "link" && item.href === "/wishlist"),
      false,
    );
  });

  it("keeps wishlist when feature is enabled", () => {
    const items = resolveAuthenticatedAccountMenuItems({
      preset: "full",
      wishlistEnabled: true,
      featuresReady: true,
    });

    assert.equal(
      items.some((item) => item.type === "link" && item.href === "/wishlist"),
      true,
    );
  });
});

describe("account menu presentation — mobile header wiring", () => {
  const guest = resolveStorefrontNavAudience({ isLoggedIn: false });
  const customer = resolveStorefrontNavAudience({ isLoggedIn: true });

  it("uses guest dropdown menu on mobile", () => {
    const presentation = resolveAccountMenuPresentation(guest, "mobile");
    assert.equal("kind" in presentation, false);
    if ("kind" in presentation) {
      assert.fail("Expected mobile guest menu presentation");
    }
    assert.equal(presentation.guestBehavior, "menu");
    assert.equal(presentation.preset, "header");
    assert.equal(presentation.showLabel, false);
  });

  it("uses compact authenticated menu on mobile", () => {
    const presentation = resolveAccountMenuPresentation(customer, "mobile");
    assert.equal("kind" in presentation, false);
    if ("kind" in presentation) {
      assert.fail("Expected mobile customer menu presentation");
    }
    assert.equal(presentation.guestBehavior, "link");
    assert.equal(presentation.preset, "header");
    assert.equal(presentation.showLabel, false);
  });

  it("keeps desktop guest auth links separate from mobile icon menu", () => {
    const presentation = resolveAccountMenuPresentation(guest, "desktop");
    assert.equal("kind" in presentation, true);
    if (!("kind" in presentation)) {
      assert.fail("Expected desktop guest links");
    }
    assert.equal(presentation.kind, "guest-links");
  });

  it("uses full authenticated menu on desktop", () => {
    const presentation = resolveAccountMenuPresentation(customer, "desktop");
    assert.equal("kind" in presentation, false);
    if ("kind" in presentation) {
      assert.fail("Expected desktop customer menu presentation");
    }
    assert.equal(presentation.preset, "full");
    assert.equal(presentation.showLabel, true);
  });
});

describe("account menu config — navigation actions", () => {
  it("does not include Buy from TZ or search routes in mobile header menu", () => {
    for (const item of MOBILE_HEADER_ACCOUNT_MENU_ITEMS) {
      if (item.type !== "link") continue;
      assert.doesNotMatch(item.href, /buy-from-tz/);
      assert.doesNotMatch(item.href, /search=/);
    }
  });

  it("routes guest actions to existing auth pages", () => {
    for (const item of GUEST_ACCOUNT_MENU_ITEMS) {
      assert.match(item.href, /^\/(login|register)$/);
    }
  });
});
