import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminConfigurationHealthApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminConfigurationHealthApiError";
  }
}

export type ConfigurationHealthSeverity = "critical" | "warning" | "info";
export type ConfigurationHealthStatus = "healthy" | "warning" | "critical" | "info";

export type ConfigurationHealthCheck = {
  group: string;
  status: ConfigurationHealthStatus;
  message: string;
  severity: ConfigurationHealthSeverity;
};

export type ConfigurationHealthPayload = {
  overall_score: number;
  status: "healthy" | "warning" | "critical";
  checks: ConfigurationHealthCheck[];
  summary?: {
    critical_count: number;
    warning_count: number;
    info_count: number;
    healthy_count: number;
  };
};

export function canViewConfigurationHealth(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "settings.view");
}

export async function fetchConfigurationHealth(): Promise<ConfigurationHealthPayload> {
  const response = await fetch("/api/admin/configuration-health", {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });

  let payload: {
    success?: boolean;
    message?: string;
    data?: ConfigurationHealthPayload;
  } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminConfigurationHealthApiError(
      payload.message?.trim() || "Unable to load configuration health.",
      response.status,
    );
  }

  return payload.data;
}
