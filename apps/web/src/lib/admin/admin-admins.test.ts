import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import {
  AdminAdminsApiError,
  createAdminUser,
  fetchAdminUsers,
  resolveAdminUserActions,
} from "@/lib/api/admin-admins";
import { hasAdminPermission } from "@/lib/api/admin-me";

describe("hasAdminPermission", () => {
  it("treats undefined permissions as super-admin full access", () => {
    assert.equal(hasAdminPermission(undefined, "admins.view"), true);
    assert.equal(hasAdminPermission(undefined, "admins.create"), true);
  });

  it("checks explicit permission slugs", () => {
    const permissions = ["admins.view", "admins.update"];
    assert.equal(hasAdminPermission(permissions, "admins.view"), true);
    assert.equal(hasAdminPermission(permissions, "admins.create"), false);
  });

  it("treats null permissions as no access", () => {
    assert.equal(hasAdminPermission(null, "admins.view"), false);
  });
});

describe("resolveAdminUserActions", () => {
  it("enables row actions based on granular permissions", () => {
    const actions = resolveAdminUserActions(
      ["admins.view", "admins.update", "admins.deactivate", "admins.assign_roles"],
      { targetIsSuperAdmin: false, isSelf: false },
    );

    assert.equal(actions.canView, true);
    assert.equal(actions.canUpdate, true);
    assert.equal(actions.canDeactivate, true);
    assert.equal(actions.canAssignRole, true);
    assert.equal(actions.canCreate, false);
  });

  it("blocks self-deactivation and self role changes for non-super admins", () => {
    const actions = resolveAdminUserActions(
      ["admins.deactivate", "admins.assign_roles"],
      { isSelf: true },
    );

    assert.equal(actions.canDeactivate, false);
    assert.equal(actions.canAssignRole, false);
  });

  it("blocks managing super-admin targets unless actor is super admin", () => {
    const actions = resolveAdminUserActions(["admins.update", "admins.deactivate"], {
      targetIsSuperAdmin: true,
    });

    assert.equal(actions.canUpdate, false);
    assert.equal(actions.canDeactivate, false);
  });
});

describe("admin-admins api client", () => {
  it("loads paginated admin users", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json({
        success: true,
        data: [{ id: "admin-1", name: "Ops Lead", email: "ops@example.com", is_active: true }],
        meta: { current_page: 1, last_page: 1, total: 1 },
      }),
    ) as typeof fetch;

    try {
      const result = await fetchAdminUsers({ search: "ops" });
      assert.equal(result.data.length, 1);
      assert.equal(result.data[0]?.email, "ops@example.com");
      assert.equal(result.meta?.total, 1);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("surfaces backend validation errors on create", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json(
        {
          success: false,
          message: "Only super admins can assign the administrator role.",
        },
        { status: 422 },
      ),
    ) as typeof fetch;

    try {
      await assert.rejects(
        () =>
          createAdminUser({
            name: "Blocked",
            email: "blocked@example.com",
            password: "password123",
            role_id: "role-1",
          }),
        (error: unknown) => {
          assert.ok(error instanceof AdminAdminsApiError);
          assert.match(error.message, /administrator role/i);
          assert.equal(error.status, 422);
          return true;
        },
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
