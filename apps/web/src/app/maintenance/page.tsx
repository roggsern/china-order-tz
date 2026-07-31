import { MaintenancePageContent } from "@/components/storefront/MaintenancePageContent";
import { getApiUrl } from "@/lib/config/env";
import {
  DEFAULT_MAINTENANCE_MESSAGE,
  mapMaintenanceMessage,
  mapMaintenanceStatus,
} from "@/lib/storefront/maintenance";
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Maintenance — CHINA ORDER TZ",
  description: "The store is temporarily unavailable for maintenance.",
  robots: { index: false, follow: false },
};

async function loadInitialMessage(): Promise<string> {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return DEFAULT_MAINTENANCE_MESSAGE;
  }

  try {
    const response = await fetch(`${apiUrl}/api/v1/storefront/maintenance`, {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    const payload = (await response.json()) as {
      data?: { maintenance?: boolean; message?: string | null };
    };
    const status = mapMaintenanceStatus(payload.data);
    return mapMaintenanceMessage(status.message);
  } catch {
    return DEFAULT_MAINTENANCE_MESSAGE;
  }
}

export default async function MaintenancePage() {
  const message = await loadInitialMessage();

  return <MaintenancePageContent initialMessage={message} />;
}
