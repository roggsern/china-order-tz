import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminNavItems } from "@/components/admin/admin-nav-items";
import {
  brandingUploadReady,
  canCreateStores,
  canUpdateStores,
  canViewStores,
  mapAdminStoreFormValues,
  mapAdminStoreListItem,
  toCreateStorePayload,
  toUpdateStorePayload,
} from "@/lib/admin/admin-stores";
import { hasAdminPermission } from "@/lib/api/admin-me";

describe("admin stores mapping", () => {
  it("gates list/create/update visibility by permission", () => {
    assert.equal(canViewStores(undefined), true);
    assert.equal(canViewStores(["stores.view"]), true);
    assert.equal(canViewStores(["stores.manage"]), false);
    assert.equal(canCreateStores(["stores.create"]), true);
    assert.equal(canCreateStores(["stores.view"]), false);
    assert.equal(canUpdateStores(["stores.update"]), true);
    assert.equal(canUpdateStores(["stores.view"]), false);
  });

  it("maps list rows for Store Manager table", () => {
    const row = mapAdminStoreListItem({
      id: "s1",
      code: "ZION",
      name: "Zion",
      slug: "zion",
      description: null,
      logo_path: "stores/s1/logo.png",
      logo_url: "https://cdn.example/logo.png",
      banner_path: null,
      banner_url: null,
      theme_color: "#123456",
      is_active: true,
      storefront_enabled: true,
      storefront_visible: true,
      storefront_featured: false,
      storefront_sort_order: 1,
      sort_order: 1,
      created_at: "2026-07-29T00:00:00+00:00",
      updated_at: "2026-07-29T00:00:00+00:00",
    });

    assert.equal(row.name, "Zion");
    assert.equal(row.slug, "zion");
    assert.equal(row.logoUrl, "https://cdn.example/logo.png");
    assert.equal(row.statusLabel, "Active");
  });

  it("maps create/update payloads from form values", () => {
    const form = mapAdminStoreFormValues(null);
    form.name = " Peachy ";
    form.code = "peachy";
    form.slug = "peachy-store";
    form.description = "Local";
    form.themeColor = "#abcdef";
    form.isActive = true;

    const createPayload = toCreateStorePayload(form);
    assert.equal(createPayload.code, "PEACHY");
    assert.equal(createPayload.name, "Peachy");
    assert.equal(createPayload.slug, "peachy-store");
    assert.equal(createPayload.theme_color, "#abcdef");

    const updatePayload = toUpdateStorePayload({
      ...form,
      name: "Peachy Renamed",
    });
    assert.equal(updatePayload.name, "Peachy Renamed");
    assert.equal("code" in updatePayload, false);
  });

  it("requires a logo or banner file before branding upload", () => {
    assert.equal(brandingUploadReady({ logo: null, banner: null }), false);
    assert.equal(
      brandingUploadReady({
        logo: { name: "logo.png" } as File,
        banner: null,
      }),
      true,
    );
  });

  it("exposes Admin → Stores nav behind stores.view", () => {
    const nav = adminNavItems.find((item) => item.href === "/admin/stores");
    assert.ok(nav);
    assert.equal(nav?.permission, "stores.view");
    assert.equal(hasAdminPermission(["stores.view"], nav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["catalog.view"], nav?.permission ?? ""), false);
  });
});
