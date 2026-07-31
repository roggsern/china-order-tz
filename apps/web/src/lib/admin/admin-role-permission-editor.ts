import { hasAdminPermission } from "@/lib/api/admin-me";
import type { AdminRoleDetail } from "@/lib/api/admin-roles";
import type {
  AdminPermissionCatalogEntry,
  AdminPermissionCatalogFilters,
  PermissionRiskTier,
} from "@/lib/admin/admin-permission-catalog";
import {
  filterPermissionCatalog,
  groupPermissionCatalogByDomain,
} from "@/lib/admin/admin-permission-catalog";

export type RolePermissionDraft = {
  add: string[];
  remove: string[];
};

export type RolePermissionImpactWarning = {
  code: string;
  label: string;
  message: string;
  permissions: string[];
};

export type RolePermissionPreviewPermission = {
  id: string;
  slug: string;
  domain: string;
  name: string;
  description?: string | null;
  risk_tier: PermissionRiskTier;
};

export type RolePermissionPreview = {
  role: {
    id: string;
    name: string;
    slug: string;
    users_count: number;
    permissions_count: number;
  };
  affected_admins: number;
  added_permissions: RolePermissionPreviewPermission[];
  removed_permissions: RolePermissionPreviewPermission[];
  warnings: RolePermissionImpactWarning[];
};

export type PermissionSaveConfirmation = {
  title: string;
  message: string;
  showHighRiskWarning: boolean;
  highRiskMessage: string;
};

export function canManageRolePermissions(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "roles.manage_permissions");
}

export function collectRolePermissionSlugs(detail: AdminRoleDetail): string[] {
  const slugs = new Set<string>();

  for (const group of detail.permissions_by_domain) {
    for (const permission of group.permissions) {
      slugs.add(permission.slug);
    }
  }

  return [...slugs].sort((a, b) => a.localeCompare(b));
}

export function computePermissionDraft(
  baselineSlugs: string[],
  selectedSlugs: string[],
): RolePermissionDraft {
  const baseline = new Set(baselineSlugs);
  const selected = new Set(selectedSlugs);

  return {
    add: [...selected].filter((slug) => !baseline.has(slug)).sort((a, b) => a.localeCompare(b)),
    remove: [...baseline].filter((slug) => !selected.has(slug)).sort((a, b) => a.localeCompare(b)),
  };
}

export function hasPermissionDraftChanges(draft: RolePermissionDraft): boolean {
  return draft.add.length > 0 || draft.remove.length > 0;
}

export function togglePermissionSlug(selectedSlugs: string[], slug: string): string[] {
  const selected = new Set(selectedSlugs);

  if (selected.has(slug)) {
    selected.delete(slug);
  } else {
    selected.add(slug);
  }

  return [...selected].sort((a, b) => a.localeCompare(b));
}

export function buildPermissionEditorGroups(
  catalog: AdminPermissionCatalogEntry[],
  filters: AdminPermissionCatalogFilters,
): ReturnType<typeof groupPermissionCatalogByDomain> {
  return groupPermissionCatalogByDomain(filterPermissionCatalog(catalog, filters));
}

export function previewHasHighRiskAdded(preview: RolePermissionPreview): boolean {
  if (preview.added_permissions.some((permission) => permission.risk_tier === "high")) {
    return true;
  }

  return preview.warnings.some((warning) => warning.code === "HIGH_RISK_PERMISSION_ADDED");
}

export function resolvePermissionSaveConfirmation(
  preview: RolePermissionPreview,
): PermissionSaveConfirmation {
  const addedCount = preview.added_permissions.length;
  const removedCount = preview.removed_permissions.length;
  const showHighRiskWarning = previewHasHighRiskAdded(preview);

  return {
    title: "Confirm permission changes",
    message: `Apply ${addedCount} addition${addedCount === 1 ? "" : "s"} and ${removedCount} removal${removedCount === 1 ? "" : "s"} to ${preview.role.name}? ${preview.affected_admins} active admin${preview.affected_admins === 1 ? "" : "s"} may be affected.`,
    showHighRiskWarning,
    highRiskMessage:
      "This change includes high-risk permissions. Verify the impact before continuing.",
  };
}

export function permissionEditorSummary(draft: RolePermissionDraft): string {
  if (!hasPermissionDraftChanges(draft)) {
    return "No pending changes.";
  }

  const parts: string[] = [];

  if (draft.add.length > 0) {
    parts.push(`${draft.add.length} to add`);
  }

  if (draft.remove.length > 0) {
    parts.push(`${draft.remove.length} to remove`);
  }

  return parts.join(", ");
}
