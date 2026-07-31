import { hasAdminPermission } from "@/lib/api/admin-me";

/** Individual dashboard module keys (persisted in preferences). */
export const ADMIN_DASHBOARD_SECTION_KEYS = [
  "attention_required",
  "business_overview",
  "operations_traffic",
  "china_pipeline",
  "tz_local_pipeline",
  "storefront_traffic",
  "storefront_conversion",
  "growth_intelligence",
  "financial_summary",
  "reports_trends",
  "top_products_activity",
  "quick_exports",
] as const;

export type AdminDashboardSectionKey = (typeof ADMIN_DASHBOARD_SECTION_KEYS)[number];

export type AdminDashboardGroupKey =
  | "attention_required"
  | "business_overview"
  | "operations"
  | "growth_intelligence"
  | "detailed_analytics";

export type AdminDashboardGroup = {
  key: AdminDashboardGroupKey;
  title: string;
  description: string;
  sections: AdminDashboardSectionKey[];
  /** RBAC permission required to show this group (defaults to reports.view). */
  permission?: string;
};

/** Default section ordering grouped for the command center layout. */
export const ADMIN_DASHBOARD_GROUPS: AdminDashboardGroup[] = [
  {
    key: "attention_required",
    title: "Attention required",
    description: "Operational exceptions that need a human decision.",
    sections: ["attention_required"],
  },
  {
    key: "business_overview",
    title: "Business overview",
    description: "Today's commercial pulse and customer activity.",
    sections: ["business_overview"],
  },
  {
    key: "operations",
    title: "Operations",
    description: "Fulfilment queues, warehouse, shipments, and channel pipelines.",
    sections: ["operations_traffic", "china_pipeline", "tz_local_pipeline"],
  },
  {
    key: "growth_intelligence",
    title: "Growth intelligence",
    description: "Storefront traffic, conversion, and business growth signals.",
    sections: ["storefront_traffic", "storefront_conversion", "growth_intelligence"],
  },
  {
    key: "detailed_analytics",
    title: "Detailed analytics",
    description: "Financial performance, trends, catalog leaders, and exports.",
    sections: ["financial_summary", "reports_trends", "top_products_activity", "quick_exports"],
  },
];

export const DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER: AdminDashboardSectionKey[] =
  ADMIN_DASHBOARD_GROUPS.flatMap((group) => group.sections);

const SECTION_PERMISSIONS: Partial<Record<AdminDashboardSectionKey, string>> = {
  attention_required: "reports.view",
  business_overview: "reports.view",
  operations_traffic: "reports.view",
  china_pipeline: "reports.view",
  tz_local_pipeline: "reports.view",
  storefront_traffic: "reports.view",
  storefront_conversion: "reports.view",
  growth_intelligence: "reports.view",
  financial_summary: "reports.view",
  reports_trends: "reports.view",
  top_products_activity: "reports.view",
  quick_exports: "reports.view",
};

export function canAccessDashboardSection(
  permissions: string[] | undefined,
  section: AdminDashboardSectionKey,
): boolean {
  const required = SECTION_PERMISSIONS[section] ?? "reports.view";
  return hasAdminPermission(permissions, required);
}

export function filterAccessibleSections(
  permissions: string[] | undefined,
  sections: AdminDashboardSectionKey[],
): AdminDashboardSectionKey[] {
  return sections.filter((section) => canAccessDashboardSection(permissions, section));
}

export function resolveVisibleGroups(
  permissions: string[] | undefined,
  hiddenSections: AdminDashboardSectionKey[],
): AdminDashboardGroup[] {
  const hidden = new Set(hiddenSections);

  return ADMIN_DASHBOARD_GROUPS.map((group) => ({
    ...group,
    sections: group.sections.filter(
      (section) => !hidden.has(section) && canAccessDashboardSection(permissions, section),
    ),
  })).filter((group) => group.sections.length > 0);
}

export function resolveSectionOrder(
  preferredOrder: AdminDashboardSectionKey[] | undefined,
  permissions: string[] | undefined,
  hiddenSections: AdminDashboardSectionKey[],
): AdminDashboardSectionKey[] {
  const hidden = new Set(hiddenSections);
  const accessible = DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER.filter(
    (section) => !hidden.has(section) && canAccessDashboardSection(permissions, section),
  );
  const accessibleSet = new Set(accessible);

  if (!preferredOrder?.length) {
    return accessible;
  }

  const ordered: AdminDashboardSectionKey[] = [];
  for (const section of preferredOrder) {
    if (accessibleSet.has(section) && !ordered.includes(section)) {
      ordered.push(section);
    }
  }

  for (const section of accessible) {
    if (!ordered.includes(section)) {
      ordered.push(section);
    }
  }

  return ordered;
}

export function groupLabelForSection(section: AdminDashboardSectionKey): string {
  for (const group of ADMIN_DASHBOARD_GROUPS) {
    if (group.sections.includes(section)) {
      return group.title;
    }
  }

  return "Dashboard";
}
