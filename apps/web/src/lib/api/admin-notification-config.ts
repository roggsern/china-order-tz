import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminNotificationConfigApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminNotificationConfigApiError";
  }
}

export type NotificationChannelToggles = {
  email_enabled: boolean;
  sms_enabled: boolean;
  whatsapp_enabled: boolean;
  push_enabled: boolean;
  in_app_enabled: boolean;
};

export type NotificationProviderStatus = {
  configured: boolean;
  driver?: string;
};

export type AdminNotificationConfig = {
  channels: NotificationChannelToggles;
  event_channel_map: Record<string, string[]>;
  provider_status?: Record<string, NotificationProviderStatus>;
  managed_events?: string[];
  allowed_channels?: string[];
};

export type UpdateAdminNotificationConfigInput = {
  channels?: Partial<NotificationChannelToggles>;
  event_channel_map?: Record<string, string[]>;
};

async function parseJson<T>(response: Response): Promise<T> {
  try {
    return (await response.json()) as T;
  } catch {
    return {} as T;
  }
}

function throwFromPayload(
  response: Response,
  payload: { message?: string; errors?: Record<string, string[]> },
  fallback: string,
): never {
  const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
  throw new AdminNotificationConfigApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export function canViewNotificationConfig(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "notifications.view");
}

export function canManageNotificationConfig(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "notifications.manage");
}

export async function fetchAdminNotificationConfig(): Promise<AdminNotificationConfig> {
  const response = await fetch("/api/admin/notifications/config", {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminNotificationConfig;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load notification configuration.");
  }

  if (!payload.data) {
    throw new AdminNotificationConfigApiError("Invalid notification config response.", response.status);
  }

  return payload.data;
}

export async function updateAdminNotificationConfig(
  input: UpdateAdminNotificationConfigInput,
): Promise<AdminNotificationConfig> {
  const response = await fetch("/api/admin/notifications/config", {
    method: "PUT",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminNotificationConfig;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to update notification configuration.");
  }

  if (!payload.data) {
    throw new AdminNotificationConfigApiError("Invalid notification config response.", response.status);
  }

  return payload.data;
}

export const CHANNEL_TOGGLE_LABELS: Record<keyof NotificationChannelToggles, string> = {
  in_app_enabled: "In-App",
  email_enabled: "Email",
  sms_enabled: "SMS",
  whatsapp_enabled: "WhatsApp",
  push_enabled: "Push",
};

export const EVENT_LABELS: Record<string, string> = {
  "order.created": "Order created",
  "order.paid": "Order paid",
  "shipment.delivered": "Shipment delivered",
};
