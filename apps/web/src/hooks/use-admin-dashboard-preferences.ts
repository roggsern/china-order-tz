"use client";

import { useCallback, useEffect, useState } from "react";
import {
  readAdminDashboardPreferences,
  toggleDashboardSectionCollapsed,
  toggleDashboardSectionHidden,
  writeAdminDashboardPreferences,
  type AdminDashboardPreferences,
} from "@/lib/admin/admin-dashboard-preferences";
import {
  resolveSectionOrder,
  type AdminDashboardSectionKey,
} from "@/lib/admin/admin-dashboard-sections";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

export function useAdminDashboardPreferences() {
  const { permissions } = useAdminPermissions();
  const [preferences, setPreferences] = useState<AdminDashboardPreferences>(() =>
    readAdminDashboardPreferences(),
  );

  useEffect(() => {
    writeAdminDashboardPreferences(preferences);
  }, [preferences]);

  const visibleSectionOrder = resolveSectionOrder(
    preferences.sectionOrder,
    permissions,
    preferences.hiddenSections,
  );

  const toggleCollapsed = useCallback((section: AdminDashboardSectionKey) => {
    setPreferences((current) => toggleDashboardSectionCollapsed(current, section));
  }, []);

  const toggleHidden = useCallback((section: AdminDashboardSectionKey) => {
    setPreferences((current) => toggleDashboardSectionHidden(current, section));
  }, []);

  const isCollapsed = useCallback(
    (section: AdminDashboardSectionKey) => preferences.collapsedSections.includes(section),
    [preferences.collapsedSections],
  );

  return {
    preferences,
    visibleSectionOrder,
    toggleCollapsed,
    toggleHidden,
    isCollapsed,
  };
}
