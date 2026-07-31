export const DEFAULT_MAINTENANCE_MESSAGE =
  "The store is temporarily unavailable for maintenance. Please try again shortly.";

export type MaintenanceStatusPayload = {
  maintenance: boolean;
  message: string | null;
};

export type MaintenanceBlockedPayload = {
  success?: boolean;
  maintenance?: boolean;
  code?: string;
  message?: string;
};

/**
 * Map API maintenance payloads to a safe UI message (no settings internals).
 */
export function mapMaintenanceMessage(
  message: string | null | undefined,
  fallback: string = DEFAULT_MAINTENANCE_MESSAGE,
): string {
  if (typeof message === "string" && message.trim()) {
    return message.trim();
  }
  return fallback;
}

export function mapMaintenanceStatus(
  input: Partial<MaintenanceStatusPayload> | MaintenanceBlockedPayload | null | undefined,
): MaintenanceStatusPayload {
  if (!input || typeof input !== "object") {
    return { maintenance: false, message: null };
  }

  const maintenance = Boolean(
    "maintenance" in input ? input.maintenance : false,
  );

  if (!maintenance) {
    return { maintenance: false, message: null };
  }

  return {
    maintenance: true,
    message: mapMaintenanceMessage(
      "message" in input ? input.message : null,
    ),
  };
}

export function isMaintenanceBlockedResponse(
  statusCode: number,
  payload: MaintenanceBlockedPayload | null | undefined,
): boolean {
  if (statusCode !== 503) {
    return false;
  }
  if (!payload || typeof payload !== "object") {
    return false;
  }
  return payload.maintenance === true || payload.code === "maintenance_mode";
}

export async function fetchStorefrontMaintenanceStatus(): Promise<MaintenanceStatusPayload> {
  try {
    const response = await fetch("/api/storefront/maintenance", {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    const payload = (await response.json()) as {
      data?: Partial<MaintenanceStatusPayload>;
    };
    return mapMaintenanceStatus(payload.data);
  } catch {
    return { maintenance: false, message: null };
  }
}
