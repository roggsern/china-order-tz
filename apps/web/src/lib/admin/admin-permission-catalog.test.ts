import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import {
  canViewPermissionCatalog,
  filterPermissionCatalog,
  groupPermissionCatalogByDomain,
  permissionRiskLabel,
} from "@/lib/admin/admin-permission-catalog";
import { fetchAdminPermissionCatalog } from "@/lib/api/admin-permissions";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";

const samplePermissions = [
  {
    id: "p1",
    name: "Orders View",
    slug: "orders.view",
    domain: "orders",
    description: "View orders",
    risk_tier: "low" as const,
  },
  {
    id: "p2",
    name: "Orders Mark Paid",
    slug: "orders.mark_paid",
    domain: "orders",
    description: "Mark orders paid",
    risk_tier: "high" as const,
  },
  {
    id: "p3",
    name: "Catalog Update",
    slug: "catalog.update",
    domain: "catalog",
    description: "Update catalog",
    risk_tier: "medium" as const,
  },
];

describe("admin permission catalog helpers", () => {
  it("gates catalog visibility with roles.manage_permissions", () => {
    assert.equal(canViewPermissionCatalog(undefined), true);
    assert.equal(canViewPermissionCatalog(["roles.manage_permissions"]), true);
    assert.equal(canViewPermissionCatalog(["admins.view"]), false);
  });

  it("filters by search, domain, and risk", () => {
    const filtered = filterPermissionCatalog(samplePermissions, {
      search: "mark",
      domain: "orders",
      risk: "high",
    });

    assert.equal(filtered.length, 1);
    assert.equal(filtered[0]?.slug, "orders.mark_paid");
  });

  it("groups permissions by domain and sorts high risk first", () => {
    const grouped = groupPermissionCatalogByDomain(samplePermissions);
    assert.equal(grouped.length, 2);
    assert.equal(grouped[0]?.domain, "catalog");
    assert.equal(grouped[1]?.permissions[0]?.slug, "orders.mark_paid");
    assert.equal(permissionRiskLabel("high"), "HIGH");
  });

  it("exposes Settings → Permissions nav item behind roles.manage_permissions", () => {
    const permissionsNav = adminSettingsNavItems.find(
      (item) => item.href === "/admin/settings/permissions",
    );
    assert.ok(permissionsNav);
    assert.equal(permissionsNav?.permission, "roles.manage_permissions");
    assert.equal(hasAdminPermission(["roles.manage_permissions"], permissionsNav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["admins.view"], permissionsNav?.permission ?? ""), false);
  });
});

describe("admin permissions api client", () => {
  it("loads permission catalog with risk metadata", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json({
        success: true,
        data: {
          permissions: samplePermissions,
          permissions_by_domain: [{ domain: "orders", permissions: samplePermissions.slice(0, 2) }],
        },
      }),
    ) as typeof fetch;

    try {
      const catalog = await fetchAdminPermissionCatalog();
      assert.equal(catalog.permissions.length, 3);
      assert.equal(catalog.permissions[1]?.risk_tier, "high");
      assert.equal(catalog.permissions_by_domain[0]?.domain, "orders");
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
