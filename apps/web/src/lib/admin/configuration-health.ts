import type {
  ConfigurationHealthCheck,
  ConfigurationHealthPayload,
  ConfigurationHealthSeverity,
  ConfigurationHealthStatus,
} from "@/lib/api/admin-configuration-health";
import { canViewConfigurationHealth } from "@/lib/api/admin-configuration-health";

export { canViewConfigurationHealth };

export type ConfigurationHealthGroupView = {
  group: string;
  title: string;
  status: ConfigurationHealthStatus;
  severity: ConfigurationHealthSeverity;
  checks: ConfigurationHealthCheck[];
};

export type ConfigurationHealthReportView = {
  overallScore: number;
  status: "healthy" | "warning" | "critical";
  groups: ConfigurationHealthGroupView[];
  warnings: ConfigurationHealthCheck[];
  criticalIssues: ConfigurationHealthCheck[];
  summary: {
    criticalCount: number;
    warningCount: number;
    infoCount: number;
    healthyCount: number;
  };
};

const GROUP_TITLES: Record<string, string> = {
  payments: "Payments",
  shipping: "Shipping",
  notifications: "Notifications",
  store: "Store",
  features: "Features",
  security: "Security",
};

const STATUS_RANK: Record<ConfigurationHealthStatus, number> = {
  critical: 3,
  warning: 2,
  info: 1,
  healthy: 0,
};

function asCheck(value: unknown): ConfigurationHealthCheck | null {
  if (!value || typeof value !== "object") {
    return null;
  }
  const row = value as Partial<ConfigurationHealthCheck>;
  if (typeof row.group !== "string" || typeof row.message !== "string") {
    return null;
  }

  const status: ConfigurationHealthStatus =
    row.status === "critical" ||
    row.status === "warning" ||
    row.status === "healthy" ||
    row.status === "info"
      ? row.status
      : "info";
  const severity: ConfigurationHealthSeverity =
    row.severity === "critical" || row.severity === "warning" || row.severity === "info"
      ? row.severity
      : status === "healthy"
        ? "info"
        : "info";

  return {
    group: row.group,
    status,
    message: row.message,
    severity,
  };
}

function worstStatus(checks: ConfigurationHealthCheck[]): ConfigurationHealthStatus {
  let worst: ConfigurationHealthStatus = "healthy";
  for (const check of checks) {
    if (STATUS_RANK[check.status] > STATUS_RANK[worst]) {
      worst = check.status;
    }
  }
  return worst;
}

export function mapConfigurationHealthPayload(
  payload: ConfigurationHealthPayload,
): ConfigurationHealthReportView {
  const checks = (Array.isArray(payload.checks) ? payload.checks : [])
    .map(asCheck)
    .filter((row): row is ConfigurationHealthCheck => row !== null);

  const byGroup = new Map<string, ConfigurationHealthCheck[]>();
  for (const check of checks) {
    const list = byGroup.get(check.group) ?? [];
    list.push(check);
    byGroup.set(check.group, list);
  }

  const groups: ConfigurationHealthGroupView[] = Array.from(byGroup.entries()).map(
    ([group, groupChecks]) => {
      const status = worstStatus(groupChecks);
      return {
        group,
        title: GROUP_TITLES[group] ?? group,
        status,
        severity:
          status === "healthy"
            ? "info"
            : status === "info"
              ? "info"
              : status,
        checks: groupChecks,
      };
    },
  );

  groups.sort((a, b) => STATUS_RANK[b.status] - STATUS_RANK[a.status] || a.title.localeCompare(b.title));

  const criticalIssues = checks.filter((check) => check.status === "critical");
  const warnings = checks.filter((check) => check.status === "warning");

  return {
    overallScore: Math.max(0, Math.min(100, Number(payload.overall_score ?? 0))),
    status:
      payload.status === "critical" || payload.status === "warning" || payload.status === "healthy"
        ? payload.status
        : criticalIssues.length > 0
          ? "critical"
          : warnings.length > 0
            ? "warning"
            : "healthy",
    groups,
    warnings,
    criticalIssues,
    summary: {
      criticalCount: Number(payload.summary?.critical_count ?? criticalIssues.length),
      warningCount: Number(payload.summary?.warning_count ?? warnings.length),
      infoCount: Number(payload.summary?.info_count ?? 0),
      healthyCount: Number(payload.summary?.healthy_count ?? 0),
    },
  };
}

export function configurationHealthScoreTone(score: number): string {
  if (score >= 85) return "text-emerald-300";
  if (score >= 60) return "text-amber-300";
  return "text-red-300";
}

export function configurationHealthStatusTone(status: ConfigurationHealthStatus): string {
  switch (status) {
    case "critical":
      return "border-red-900/60 bg-red-950/30 text-red-200";
    case "warning":
      return "border-amber-900/60 bg-amber-950/30 text-amber-200";
    case "healthy":
      return "border-emerald-900/60 bg-emerald-950/30 text-emerald-200";
    default:
      return "border-zinc-700 bg-zinc-900/50 text-zinc-300";
  }
}
