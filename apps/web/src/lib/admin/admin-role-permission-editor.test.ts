import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import {
  buildPermissionEditorGroups,
  canManageRolePermissions,
  collectRolePermissionSlugs,
  computePermissionDraft,
  hasPermissionDraftChanges,
  permissionEditorSummary,
  previewHasHighRiskAdded,
  resolvePermissionSaveConfirmation,
  togglePermissionSlug,
  type RolePermissionPreview,
} from "@/lib/admin/admin-role-permission-editor";
import {
  previewRolePermissionChanges,
  updateRolePermissions,
} from "@/lib/api/admin-roles";

const sampleCatalog = [
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
    name: "Orders Cancel",
    slug: "orders.cancel",
    domain: "orders",
    description: "Cancel orders",
    risk_tier: "high" as const,
  },
  {
    id: "p3",
    name: "Admins View",
    slug: "admins.view",
    domain: "admins",
    description: "View admins",
    risk_tier: "low" as const,
  },
];

const sampleDetail = {
  role: {
    id: "role-1",
    name: "Support",
    slug: "support",
    users_count: 2,
    permissions_count: 2,
  },
  assigned_admins: [],
  permissions_by_domain: [
    {
      domain: "orders",
      permissions: [
        {
          id: "p1",
          name: "Orders View",
          slug: "orders.view",
          domain: "orders",
          risk_tier: "low" as const,
        },
      ],
    },
    {
      domain: "admins",
      permissions: [
        {
          id: "p3",
          name: "Admins View",
          slug: "admins.view",
          domain: "admins",
          risk_tier: "low" as const,
        },
      ],
    },
  ],
};

const samplePreview: RolePermissionPreview = {
  role: sampleDetail.role,
  affected_admins: 2,
  added_permissions: [
    {
      id: "p2",
      slug: "orders.cancel",
      domain: "orders",
      name: "Orders Cancel",
      risk_tier: "high",
    },
  ],
  removed_permissions: [
    {
      id: "p3",
      slug: "admins.view",
      domain: "admins",
      name: "Admins View",
      risk_tier: "low",
    },
  ],
  warnings: [
    {
      code: "HIGH_RISK_PERMISSION_ADDED",
      label: "High risk permission added",
      message: "One or more high-risk permissions would be granted to this role.",
      permissions: ["orders.cancel"],
    },
    {
      code: "ADMIN_ACCESS_REDUCTION",
      label: "Admin access reduction",
      message: "This change would reduce admin access for users assigned to this role.",
      permissions: ["admins.view"],
    },
  ],
};

describe("admin role permission editor helpers", () => {
  it("gates editor access with roles.manage_permissions", () => {
    assert.equal(canManageRolePermissions(undefined), true);
    assert.equal(canManageRolePermissions(["roles.manage_permissions"]), true);
    assert.equal(canManageRolePermissions(["admins.view"]), false);
  });

  it("collects current role permission slugs from role detail", () => {
    assert.deepEqual(collectRolePermissionSlugs(sampleDetail), ["admins.view", "orders.view"]);
  });

  it("computes add/remove draft from baseline and selected slugs", () => {
    const draft = computePermissionDraft(
      ["orders.view", "admins.view"],
      ["orders.view", "orders.cancel"],
    );

    assert.deepEqual(draft, {
      add: ["orders.cancel"],
      remove: ["admins.view"],
    });
    assert.equal(hasPermissionDraftChanges(draft), true);
    assert.equal(
      hasPermissionDraftChanges(computePermissionDraft(["orders.view"], ["orders.view"])),
      false,
    );
  });

  it("toggles checkbox selection and summarizes draft changes", () => {
    const toggled = togglePermissionSlug(["orders.view"], "orders.cancel");
    assert.deepEqual(toggled, ["orders.cancel", "orders.view"]);
    assert.equal(permissionEditorSummary({ add: ["orders.cancel"], remove: [] }), "1 to add");
    assert.equal(
      permissionEditorSummary({ add: ["orders.cancel"], remove: ["admins.view"] }),
      "1 to add, 1 to remove",
    );
  });

  it("builds grouped editor rows from catalog filters", () => {
    const grouped = buildPermissionEditorGroups(sampleCatalog, {
      search: "orders",
      domain: "orders",
      risk: "high",
    });

    assert.equal(grouped.length, 1);
    assert.equal(grouped[0]?.permissions[0]?.slug, "orders.cancel");
  });

  it("detects high-risk preview changes and confirmation copy", () => {
    assert.equal(previewHasHighRiskAdded(samplePreview), true);

    const confirmation = resolvePermissionSaveConfirmation(samplePreview);
    assert.equal(confirmation.title, "Confirm permission changes");
    assert.equal(confirmation.showHighRiskWarning, true);
    assert.match(confirmation.message, /2 active admins/);
    assert.match(confirmation.highRiskMessage, /high-risk permissions/i);
  });

  it("builds warning modal state without high risk when preview is safe", () => {
    const safePreview: RolePermissionPreview = {
      ...samplePreview,
      added_permissions: [
        {
          id: "p4",
          slug: "orders.update",
          domain: "orders",
          name: "Orders Update",
          risk_tier: "medium",
        },
      ],
      warnings: [],
    };

    assert.equal(previewHasHighRiskAdded(safePreview), false);
    assert.equal(resolvePermissionSaveConfirmation(safePreview).showHighRiskWarning, false);
  });
});

describe("admin role permission editor api client", () => {
  it("loads preview impact before save", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json({ success: true, data: samplePreview }),
    ) as typeof fetch;

    try {
      const preview = await previewRolePermissionChanges("role-1", {
        add: ["orders.cancel"],
        remove: ["admins.view"],
      });

      assert.equal(preview.affected_admins, 2);
      assert.equal(preview.warnings[0]?.code, "HIGH_RISK_PERMISSION_ADDED");
      assert.equal(preview.added_permissions[0]?.risk_tier, "high");
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("saves permission diff through PATCH endpoint", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async (input, init) => {
      assert.equal(String(input), "/api/admin/roles/role-1/permissions");
      assert.equal(init?.method, "PATCH");
      assert.deepEqual(JSON.parse(String(init?.body)), {
        add: ["orders.cancel"],
        remove: ["admins.view"],
      });

      return Response.json({ success: true, data: sampleDetail });
    }) as typeof fetch;

    try {
      const updated = await updateRolePermissions("role-1", {
        add: ["orders.cancel"],
        remove: ["admins.view"],
      });

      assert.equal(updated.role.slug, "support");
      assert.equal(updated.permissions_by_domain.length, 2);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
