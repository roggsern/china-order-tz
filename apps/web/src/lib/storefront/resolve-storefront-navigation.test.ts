import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { CmsNavigationAllPayload, CmsNavigationItem } from "../api/cms-navigation";
import {
  mapAudienceToCmsAudience,
  mapCmsItemToResolved,
  policyItemToResolved,
  resolveCtaHref,
  resolveStorefrontNavigation,
} from "./resolve-storefront-navigation";
import {
  getPrimaryNavItems,
  resolveStorefrontNavAudience,
  STOREFRONT_NAV_LABELS,
} from "./navigation-policy";

function shell(
  type: "PRIMARY" | "FOOTER" | "MOBILE",
  items: CmsNavigationItem[],
): NonNullable<CmsNavigationAllPayload["shells"]>[typeof type] {
  return {
    commerce_context: "GLOBAL",
    navigation_type: type,
    shell: {
      id: `${type.toLowerCase()}-id`,
      name: `${type} Shell`,
      slug: `${type.toLowerCase()}-shell`,
      is_default: true,
      status: "active",
    },
    campaign: null,
    items,
  };
}

describe("resolve-storefront-navigation — CTA hrefs", () => {
  it("resolves URL, product, store, and china order form CTAs", () => {
    assert.equal(
      resolveCtaHref({
        type: "URL",
        label: "About",
        value: "https://example.com/about",
        url: "https://example.com/about",
      }),
      "https://example.com/about",
    );
    assert.equal(
      resolveCtaHref({ type: "PRODUCT", label: "P", value: "widget", url: null }),
      "/products/widget",
    );
    assert.equal(
      resolveCtaHref({ type: "STORE", label: "S", value: "zion-mode", url: null }),
      "/buy-from-tz/zion-mode",
    );
    assert.equal(
      resolveCtaHref({ type: "CHINA_ORDER_FORM", label: "Order", value: null, url: null }),
      "/products?origin=china",
    );
  });
});

describe("resolve-storefront-navigation — CMS mapping", () => {
  it("maps China journey/mega to china_mega for MegaMenu reuse", () => {
    const journey = mapCmsItemToResolved({
      id: "j1",
      title: "Order from China",
      icon: null,
      position: 0,
      visibility: "PUBLIC",
      item_type: "JOURNEY",
      target_type: null,
      target_value: "CHINA_IMPORT",
      journey: {
        code: "CHINA_IMPORT",
        engine: "china_storefront_catalog",
        label: "Order from China",
      },
    });
    assert.equal(journey.kind, "china_mega");
    assert.equal(journey.policyId, "orderFromChina");
    assert.equal(journey.journey, "CHINA_IMPORT");

    const mega = mapCmsItemToResolved({
      id: "m1",
      title: "China menu",
      icon: null,
      position: 0,
      visibility: "PUBLIC",
      item_type: "MEGA_MENU",
      target_type: null,
      target_value: "CHINA_IMPORT",
      mega_menu: { engine: "china_storefront_catalog", journey: "CHINA_IMPORT", categories: [] },
    });
    assert.equal(mega.kind, "china_mega");
  });

  it("maps TZ journey/mega to tz_mega and keeps hydrated stores", () => {
    const item = mapCmsItemToResolved({
      id: "tz1",
      title: "Buy from TZ",
      icon: null,
      position: 0,
      visibility: "PUBLIC",
      item_type: "MEGA_MENU",
      target_type: null,
      target_value: "TZ_LOCAL",
      mega_menu: {
        engine: "tz_storefront_catalog",
        journey: "TZ_LOCAL",
        stores: [{ id: "1", name: "Zion Mode", slug: "zion-mode" }],
      },
    });
    assert.equal(item.kind, "tz_mega");
    assert.equal(item.policyId, "buyFromTz");
    assert.equal(item.stores?.[0]?.slug, "zion-mode");
  });
});

describe("resolve-storefront-navigation — CMS success vs fallback", () => {
  const guest = resolveStorefrontNavAudience({ isLoggedIn: false });
  const customer = resolveStorefrontNavAudience({ isLoggedIn: true });

  it("uses CMS primary items when shell exists", () => {
    const cms: CmsNavigationAllPayload = {
      commerce_context: "GLOBAL",
      campaign: null,
      shells: {
        PRIMARY: shell("PRIMARY", [
          {
            id: "1",
            title: "Order from China",
            icon: null,
            position: 0,
            visibility: "PUBLIC",
            item_type: "JOURNEY",
            target_type: null,
            target_value: "CHINA_IMPORT",
            journey: {
              code: "CHINA_IMPORT",
              engine: "china_storefront_catalog",
              label: "Order from China",
            },
          },
          {
            id: "2",
            title: "Buy from TZ",
            icon: null,
            position: 1,
            visibility: "PUBLIC",
            item_type: "JOURNEY",
            target_type: null,
            target_value: "TZ_LOCAL",
            journey: {
              code: "TZ_LOCAL",
              engine: "tz_storefront_catalog",
              label: "Buy from TZ",
            },
          },
          {
            id: "3",
            title: "About Us",
            icon: null,
            position: 2,
            visibility: "PUBLIC",
            item_type: "LINK",
            target_type: "URL",
            target_value: "/#about",
            cta: { type: "URL", label: "About Us", value: "/#about", url: "/#about" },
          },
        ]),
      },
    };

    const resolved = resolveStorefrontNavigation(cms, guest);
    assert.equal(resolved.source, "cms");
    assert.equal(resolved.primary[0]?.kind, "china_mega");
    assert.equal(resolved.primary[1]?.kind, "tz_mega");
    assert.equal(resolved.primary[2]?.kind, "link");
    assert.equal(resolved.primary[2]?.href, "/#about");
  });

  it("falls back to navigation-policy when CMS is null or empty", () => {
    const empty: CmsNavigationAllPayload = {
      commerce_context: "GLOBAL",
      campaign: null,
      shells: {
        PRIMARY: {
          commerce_context: "GLOBAL",
          navigation_type: "PRIMARY",
          shell: null,
          campaign: null,
          items: [],
        },
      },
    };

    const fromNull = resolveStorefrontNavigation(null, guest);
    const fromEmpty = resolveStorefrontNavigation(empty, guest);

    assert.equal(fromNull.source, "fallback");
    assert.equal(fromEmpty.source, "fallback");

    const policyLabels = getPrimaryNavItems(guest).map((item) => item.label);
    assert.deepEqual(
      fromNull.primary.map((item) => item.label),
      policyLabels,
    );
    assert.ok(fromNull.primary.some((item) => item.kind === "china_mega"));
    assert.ok(fromNull.primary.some((item) => item.kind === "tz_mega"));
  });

  it("builds footer Buy From TZ from CMS mega stores", () => {
    const cms: CmsNavigationAllPayload = {
      commerce_context: "GLOBAL",
      campaign: null,
      shells: {
        FOOTER: shell("FOOTER", [
          {
            id: "f1",
            title: "Buy From TZ",
            icon: null,
            position: 0,
            visibility: "PUBLIC",
            item_type: "MEGA_MENU",
            target_type: null,
            target_value: "TZ_LOCAL",
            mega_menu: {
              engine: "tz_storefront_catalog",
              journey: "TZ_LOCAL",
              stores: [
                { id: "s1", name: "Peachy Lingerie", slug: "peachy-lingerie" },
              ],
            },
          },
        ]),
      },
    };

    const resolved = resolveStorefrontNavigation(cms, guest);
    assert.equal(resolved.source, "cms");
    assert.equal(resolved.footerTzStores[0]?.slug, "peachy-lingerie");
    const tzCol = resolved.footerColumns.find((c) => c.title === "Buy From TZ");
    assert.ok(tzCol);
    assert.ok(tzCol!.links.some((l) => l.href === "/buy-from-tz/peachy-lingerie"));
  });

  it("mobile reuses the same resolver (CMS mobile or primary + policy auth extras)", () => {
    const cms: CmsNavigationAllPayload = {
      commerce_context: "GLOBAL",
      campaign: null,
      shells: {
        PRIMARY: shell("PRIMARY", [
          {
            id: "1",
            title: STOREFRONT_NAV_LABELS.orderFromChina,
            icon: null,
            position: 0,
            visibility: "PUBLIC",
            item_type: "JOURNEY",
            target_type: null,
            target_value: "CHINA_IMPORT",
            journey: {
              code: "CHINA_IMPORT",
              engine: "china_storefront_catalog",
              label: "Order from China",
            },
          },
        ]),
      },
    };

    const guestNav = resolveStorefrontNavigation(cms, guest);
    assert.ok(guestNav.mobile.some((item) => item.kind === "china_mega"));
    assert.ok(guestNav.mobile.some((item) => item.policyId === "signIn"));

    const customerNav = resolveStorefrontNavigation(cms, customer);
    assert.ok(customerNav.mobile.some((item) => item.policyId === "myAccount"));
    assert.ok(customerNav.mobile.some((item) => item.policyId === "signOut"));
    assert.equal(
      customerNav.mobile.some((item) => item.policyId === "signIn"),
      false,
    );
  });

  it("maps audience to CMS audience without inventing permission rules", () => {
    assert.equal(mapAudienceToCmsAudience(guest), "guest");
    assert.equal(mapAudienceToCmsAudience(customer), "authenticated");
    assert.equal(
      mapAudienceToCmsAudience(resolveStorefrontNavAudience({ isLoggedIn: true, isStaffPreview: true })),
      "admin_preview",
    );
  });

  it("policy fallback items keep journey kinds for mega menu components", () => {
    const items = getPrimaryNavItems(guest).map(policyItemToResolved);
    assert.equal(items.find((i) => i.policyId === "orderFromChina")?.kind, "china_mega");
    assert.equal(items.find((i) => i.policyId === "buyFromTz")?.kind, "tz_mega");
  });
});
