import {
  canViewSettingsControlCenter,
  type SettingsAuditChange,
  type SettingsDashboardPayload,
  type SettingsModuleCard,
  type SettingsModuleStatus,
  type SettingsQuickAction,
} from "@/lib/api/admin-settings-dashboard";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  configurationHealthScoreTone,
  configurationHealthStatusTone,
} from "@/lib/admin/configuration-health";

export { canViewSettingsControlCenter, configurationHealthScoreTone, configurationHealthStatusTone };

export type SettingsModuleCardView = {
  key: string;
  label: string;
  href: string;
  permission: string;
  status: SettingsModuleStatus;
  message: string;
  checkCount: number;
};

export type SettingsQuickActionView = {
  key: string;
  label: string;
  href: string;
  permission: string;
};

export type SettingsAuditChangeView = {
  id: string;
  actorName: string;
  event: string;
  eventLabel: string;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  timestamp: string | null;
  description: string | null;
};

export type SettingsDashboardView = {
  healthScore: number;
  status: "healthy" | "warning" | "critical";
  summary: {
    criticalCount: number;
    warningCount: number;
    infoCount: number;
    healthyCount: number;
  };
  modules: SettingsModuleCardView[];
  quickActions: SettingsQuickActionView[];
  recentChanges: SettingsAuditChangeView[];
};

function asStatus(value: unknown): SettingsModuleStatus {
  if (
    value === "critical" ||
    value === "warning" ||
    value === "healthy" ||
    value === "info"
  ) {
    return value;
  }
  return "info";
}

function mapModule(row: SettingsModuleCard): SettingsModuleCardView {
  return {
    key: row.key,
    label: row.label,
    href: row.href,
    permission: row.permission,
    status: asStatus(row.status),
    message: row.message,
    checkCount: Number(row.check_count ?? 0),
  };
}

function mapAction(row: SettingsQuickAction): SettingsQuickActionView {
  return {
    key: row.key,
    label: row.label,
    href: row.href,
    permission: row.permission,
  };
}

export function mapSettingsAuditChange(row: SettingsAuditChange): SettingsAuditChangeView {
  return {
    id: String(row.id ?? ""),
    actorName: row.actor?.name?.trim() || row.actor?.type || "system",
    event: row.event,
    eventLabel: row.event_label?.trim() || row.event,
    before: row.before ?? null,
    after: row.after ?? null,
    timestamp: row.timestamp ?? null,
    description: row.description ?? null,
  };
}

export function mapSettingsDashboardPayload(
  payload: SettingsDashboardPayload,
  permissions?: string[],
): SettingsDashboardView {
  const modules = (Array.isArray(payload.module_statuses) ? payload.module_statuses : [])
    .map(mapModule)
    .filter((module) => hasAdminPermission(permissions, module.permission));

  const quickActions = (Array.isArray(payload.quick_actions) ? payload.quick_actions : [])
    .map(mapAction)
    .filter((action) => hasAdminPermission(permissions, action.permission));

  const recentChanges = (Array.isArray(payload.recent_changes) ? payload.recent_changes : []).map(
    mapSettingsAuditChange,
  );

  return {
    healthScore: Math.max(0, Math.min(100, Number(payload.health_score ?? 0))),
    status:
      payload.status === "critical" ||
      payload.status === "warning" ||
      payload.status === "healthy"
        ? payload.status
        : "healthy",
    summary: {
      criticalCount: Number(payload.summary?.critical_count ?? 0),
      warningCount: Number(payload.summary?.warning_count ?? 0),
      infoCount: Number(payload.summary?.info_count ?? 0),
      healthyCount: Number(payload.summary?.healthy_count ?? 0),
    },
    modules,
    quickActions,
    recentChanges,
  };
}

export function formatSettingsAuditJson(value: Record<string, unknown> | null): string {
  if (!value) {
    return "—";
  }
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return "—";
  }
}

export function formatSettingsTimestamp(value: string | null): string {
  if (!value) {
    return "—";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleString();
}
