import { hasAdminPermission } from "@/lib/api/admin-me";
import { formatPermissionDomainLabel } from "@/lib/api/admin-roles";

export type PermissionRiskTier = "low" | "medium" | "high";

export type AdminPermissionCatalogEntry = {
  id: string;
  name: string;
  slug: string;
  domain: string;
  description?: string | null;
  risk_tier: PermissionRiskTier;
};

export type AdminPermissionDomainGroup = {
  domain: string;
  permissions: AdminPermissionCatalogEntry[];
};

export type AdminPermissionCatalogFilters = {
  search?: string;
  domain?: string;
  risk?: PermissionRiskTier | "";
  groupByDomain?: boolean;
};

export function canViewPermissionCatalog(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "roles.manage_permissions");
}

export function permissionRiskLabel(tier: PermissionRiskTier): string {
  return tier.toUpperCase();
}

export function permissionRiskBadgeClass(tier: PermissionRiskTier): string {
  switch (tier) {
    case "high":
      return "border-red-900/60 bg-red-950/40 text-red-200";
    case "medium":
      return "border-amber-900/60 bg-amber-950/40 text-amber-200";
    default:
      return "border-emerald-900/60 bg-emerald-950/40 text-emerald-200";
  }
}

export function filterPermissionCatalog(
  rows: AdminPermissionCatalogEntry[],
  filters: AdminPermissionCatalogFilters,
): AdminPermissionCatalogEntry[] {
  const search = filters.search?.trim().toLowerCase() ?? "";

  return rows.filter((row) => {
    if (filters.domain && row.domain !== filters.domain) {
      return false;
    }

    if (filters.risk && row.risk_tier !== filters.risk) {
      return false;
    }

    if (!search) {
      return true;
    }

    return (
      row.slug.toLowerCase().includes(search) ||
      row.domain.toLowerCase().includes(search) ||
      (row.description ?? "").toLowerCase().includes(search) ||
      row.name.toLowerCase().includes(search)
    );
  });
}

export function groupPermissionCatalogByDomain(
  rows: AdminPermissionCatalogEntry[],
): AdminPermissionDomainGroup[] {
  const grouped = new Map<string, AdminPermissionCatalogEntry[]>();

  for (const row of rows) {
    const list = grouped.get(row.domain) ?? [];
    list.push(row);
    grouped.set(row.domain, list);
  }

  return [...grouped.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([domain, permissions]) => ({
      domain,
      permissions: [...permissions].sort((a, b) => {
        const riskOrder = { high: 0, medium: 1, low: 2 } as const;
        const riskDiff = riskOrder[a.risk_tier] - riskOrder[b.risk_tier];
        return riskDiff !== 0 ? riskDiff : a.slug.localeCompare(b.slug);
      }),
    }));
}

export function listPermissionDomains(rows: AdminPermissionCatalogEntry[]): string[] {
  return [...new Set(rows.map((row) => row.domain))].sort((a, b) => a.localeCompare(b));
}

export { formatPermissionDomainLabel };

export function groupRolePermissionsByRisk(
  permissions: AdminPermissionCatalogEntry[],
): Record<PermissionRiskTier, AdminPermissionCatalogEntry[]> {
  return {
    high: permissions.filter((p) => p.risk_tier === "high"),
    medium: permissions.filter((p) => p.risk_tier === "medium"),
    low: permissions.filter((p) => p.risk_tier === "low"),
  };
}
