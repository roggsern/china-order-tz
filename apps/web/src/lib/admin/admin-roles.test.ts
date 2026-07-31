import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import {
  AdminRolesApiError,
  canManageRolePermissions,
  canViewAdminRoles,
  fetchAdminRole,
  fetchAdminRoles,
  formatPermissionDomainLabel,
  sortRoleSummaries,
} from "@/lib/api/admin-roles";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";

describe("admin roles read helpers", () => {
  it("formats permission domain labels", () => {
    assert.equal(formatPermissionDomainLabel("activity_logs"), "Activity Logs");
    assert.equal(formatPermissionDomainLabel("orders"), "Orders");
  });

  it("sorts role summaries by name", () => {
    const sorted = sortRoleSummaries([
      { id: "2", name: "Warehouse Officer", slug: "warehouse_officer", users_count: 1, permissions_count: 5 },
      { id: "1", name: "Administrator", slug: "administrator", users_count: 2, permissions_count: 80 },
    ]);

    assert.equal(sorted[0]?.slug, "administrator");
    assert.equal(sorted[1]?.slug, "warehouse_officer");
  });

  it("gates roles visibility with admins.view", () => {
    assert.equal(canViewAdminRoles(undefined), true);
    assert.equal(canViewAdminRoles(["admins.view"]), true);
    assert.equal(canViewAdminRoles(["orders.view"]), false);
  });

  it("gates permission editing with roles.manage_permissions", () => {
    assert.equal(canManageRolePermissions(["roles.manage_permissions"]), true);
    assert.equal(canManageRolePermissions(["admins.view"]), false);
  });

  it("exposes Settings → Roles nav item behind admins.view", () => {
    const rolesNav = adminSettingsNavItems.find((item) => item.href === "/admin/settings/roles");
    assert.ok(rolesNav);
    assert.equal(rolesNav?.permission, "admins.view");
    assert.equal(hasAdminPermission(["admins.view"], rolesNav?.permission ?? ""), true);
    assert.equal(hasAdminPermission(["orders.view"], rolesNav?.permission ?? ""), false);
  });
});

describe("admin roles api client", () => {
  it("loads role list for matrix table", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json({
        success: true,
        data: [
          {
            id: "role-1",
            name: "Manager",
            slug: "manager",
            description: "Operations manager",
            users_count: 3,
            permissions_count: 12,
          },
        ],
      }),
    ) as typeof fetch;

    try {
      const rows = await fetchAdminRoles();
      assert.equal(rows.length, 1);
      assert.equal(rows[0]?.permissions_count, 12);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("loads grouped permissions for role detail", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json({
        success: true,
        data: {
          role: {
            id: "role-1",
            name: "Manager",
            slug: "manager",
            users_count: 1,
            permissions_count: 2,
          },
          assigned_admins: [
            { id: "admin-1", name: "Ops Lead", email: "ops@example.com", is_super_admin: false, is_active: true },
          ],
          permissions_by_domain: [
            {
              domain: "orders",
              permissions: [
                { id: "p1", name: "Orders View", slug: "orders.view", domain: "orders" },
              ],
            },
          ],
        },
      }),
    ) as typeof fetch;

    try {
      const detail = await fetchAdminRole("role-1");
      assert.equal(detail.role.slug, "manager");
      assert.equal(detail.assigned_admins[0]?.email, "ops@example.com");
      assert.equal(detail.permissions_by_domain[0]?.domain, "orders");
      assert.equal(detail.permissions_by_domain[0]?.permissions[0]?.slug, "orders.view");
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("surfaces backend authorization errors", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json({ success: false, message: "Forbidden." }, { status: 403 }),
    ) as typeof fetch;

    try {
      await assert.rejects(
        () => fetchAdminRoles(),
        (error: unknown) => {
          assert.ok(error instanceof AdminRolesApiError);
          assert.equal(error.status, 403);
          return true;
        },
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
