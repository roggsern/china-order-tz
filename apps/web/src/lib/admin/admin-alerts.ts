import type { AdminAlert, AdminAlertSource } from "@/lib/api/admin-reporting";

export function formatAdminAlertSource(source: AdminAlertSource): string {
  return source === "operational" ? "Operational" : "Growth";
}

export function adminAlertSeverityBadgeClass(severity: AdminAlert["severity"]): string {
  switch (severity) {
    case "HIGH":
      return "border-red-200 bg-red-50 text-red-700";
    case "MEDIUM":
      return "border-amber-200 bg-amber-50 text-amber-800";
    case "LOW":
      return "border-emerald-200 bg-emerald-50 text-emerald-700";
    default:
      return "border-zinc-200 bg-zinc-50 text-zinc-700";
  }
}

export function filterAdminAlerts(
  alerts: AdminAlert[],
  source: AdminAlertSource | "all",
): AdminAlert[] {
  if (source === "all") {
    return alerts;
  }

  return alerts.filter((alert) => alert.source === source);
}

export function adminAlertsEmptyMessage(source: AdminAlertSource | "all"): string {
  if (source === "operational") {
    return "No operational alerts right now.";
  }
  if (source === "growth") {
    return "No growth alerts for this period.";
  }

  return "No alerts right now — operations and growth are clear.";
}
