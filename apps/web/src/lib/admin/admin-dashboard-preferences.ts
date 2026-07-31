import {
  ADMIN_DASHBOARD_SECTION_KEYS,
  DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER,
  type AdminDashboardSectionKey,
} from "@/lib/admin/admin-dashboard-sections";

const STORAGE_KEY = "china-order-tz-admin-dashboard-preferences";

export type AdminDashboardPreferences = {
  sectionOrder: AdminDashboardSectionKey[];
  collapsedSections: AdminDashboardSectionKey[];
  hiddenSections: AdminDashboardSectionKey[];
};

export const DEFAULT_ADMIN_DASHBOARD_PREFERENCES: AdminDashboardPreferences = {
  sectionOrder: [...DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER],
  collapsedSections: [],
  hiddenSections: [],
};

function isSectionKey(value: unknown): value is AdminDashboardSectionKey {
  return (
    typeof value === "string" &&
    (ADMIN_DASHBOARD_SECTION_KEYS as readonly string[]).includes(value)
  );
}

function sanitizeSectionList(value: unknown): AdminDashboardSectionKey[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter(isSectionKey);
}

export function normalizeAdminDashboardPreferences(
  raw: Partial<AdminDashboardPreferences> | null | undefined,
): AdminDashboardPreferences {
  const sectionOrder = sanitizeSectionList(raw?.sectionOrder);
  const collapsedSections = sanitizeSectionList(raw?.collapsedSections);
  const hiddenSections = sanitizeSectionList(raw?.hiddenSections);

  return {
    sectionOrder: sectionOrder.length > 0 ? sectionOrder : [...DEFAULT_ADMIN_DASHBOARD_SECTION_ORDER],
    collapsedSections,
    hiddenSections,
  };
}

export function readAdminDashboardPreferences(): AdminDashboardPreferences {
  if (typeof window === "undefined") {
    return { ...DEFAULT_ADMIN_DASHBOARD_PREFERENCES };
  }

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return { ...DEFAULT_ADMIN_DASHBOARD_PREFERENCES };
    }

    return normalizeAdminDashboardPreferences(JSON.parse(raw) as Partial<AdminDashboardPreferences>);
  } catch {
    return { ...DEFAULT_ADMIN_DASHBOARD_PREFERENCES };
  }
}

export function writeAdminDashboardPreferences(preferences: AdminDashboardPreferences): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
}

export function isDashboardSectionCollapsed(
  preferences: AdminDashboardPreferences,
  section: AdminDashboardSectionKey,
): boolean {
  return preferences.collapsedSections.includes(section);
}

export function isDashboardSectionHidden(
  preferences: AdminDashboardPreferences,
  section: AdminDashboardSectionKey,
): boolean {
  return preferences.hiddenSections.includes(section);
}

export function toggleDashboardSectionCollapsed(
  preferences: AdminDashboardPreferences,
  section: AdminDashboardSectionKey,
): AdminDashboardPreferences {
  const collapsed = new Set(preferences.collapsedSections);
  if (collapsed.has(section)) {
    collapsed.delete(section);
  } else {
    collapsed.add(section);
  }

  return {
    ...preferences,
    collapsedSections: [...collapsed],
  };
}

export function toggleDashboardSectionHidden(
  preferences: AdminDashboardPreferences,
  section: AdminDashboardSectionKey,
): AdminDashboardPreferences {
  const hidden = new Set(preferences.hiddenSections);
  if (hidden.has(section)) {
    hidden.delete(section);
  } else {
    hidden.add(section);
  }

  return {
    ...preferences,
    hiddenSections: [...hidden],
  };
}

export function reorderDashboardSections(
  preferences: AdminDashboardPreferences,
  sectionOrder: AdminDashboardSectionKey[],
): AdminDashboardPreferences {
  return {
    ...preferences,
    sectionOrder: sanitizeSectionList(sectionOrder),
  };
}
