import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminSettingsDashboardApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminSettingsDashboardApiError";
  }
}

export type SettingsModuleStatus = "healthy" | "warning" | "critical" | "info";

export type SettingsDashboardActor = {
  id: string | null;
  name: string | null;
  type: string;
};

export type SettingsAuditChange = {
  id: string;
  actor: SettingsDashboardActor | null;
  event: string;
  event_label?: string;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  timestamp: string | null;
  description?: string | null;
};

export type SettingsModuleCard = {
  key: string;
  label: string;
  href: string;
  permission: string;
  status: SettingsModuleStatus;
  message: string;
  check_count: number;
};

export type SettingsQuickAction = {
  key: string;
  label: string;
  href: string;
  permission: string;
};

export type SettingsDashboardPayload = {
  health_score: number;
  status: "healthy" | "warning" | "critical";
  summary?: {
    critical_count: number;
    warning_count: number;
    info_count: number;
    healthy_count: number;
  };
  module_statuses: SettingsModuleCard[];
  quick_actions: SettingsQuickAction[];
  recent_changes: SettingsAuditChange[];
};

export type SettingsHistoryMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type SettingsHistoryPayload = {
  data: SettingsAuditChange[];
  meta: SettingsHistoryMeta;
  filters: {
    events: string[];
  };
};

export function canViewSettingsControlCenter(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "settings.view");
}

export async function fetchSettingsDashboard(): Promise<SettingsDashboardPayload> {
  const response = await fetch("/api/admin/settings/dashboard", {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });

  let payload: {
    success?: boolean;
    message?: string;
    data?: SettingsDashboardPayload;
  } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !payload.data) {
    throw new AdminSettingsDashboardApiError(
      payload.message?.trim() || "Unable to load settings dashboard.",
      response.status,
    );
  }

  return payload.data;
}

export async function fetchSettingsHistory(params?: {
  event?: string;
  page?: number;
  perPage?: number;
}): Promise<SettingsHistoryPayload> {
  const search = new URLSearchParams();
  if (params?.event) search.set("event", params.event);
  if (params?.page) search.set("page", String(params.page));
  if (params?.perPage) search.set("per_page", String(params.perPage));

  const query = search.toString();
  const response = await fetch(
    `/api/admin/settings/history${query ? `?${query}` : ""}`,
    {
      method: "GET",
      credentials: "include",
      cache: "no-store",
    },
  );

  let payload: {
    success?: boolean;
    message?: string;
    data?: SettingsAuditChange[];
    meta?: SettingsHistoryMeta;
    filters?: { events?: string[] };
  } = {};
  try {
    payload = (await response.json()) as typeof payload;
  } catch {
    payload = {};
  }

  if (!response.ok || !Array.isArray(payload.data) || !payload.meta) {
    throw new AdminSettingsDashboardApiError(
      payload.message?.trim() || "Unable to load settings history.",
      response.status,
    );
  }

  return {
    data: payload.data,
    meta: payload.meta,
    filters: {
      events: Array.isArray(payload.filters?.events) ? payload.filters.events : [],
    },
  };
}
