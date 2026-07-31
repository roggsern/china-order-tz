import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { getApiUrl } from "@/lib/config/env";
import { mapMaintenanceStatus } from "@/lib/storefront/maintenance";
import { redirect } from "next/navigation";

async function isStorefrontInMaintenance(): Promise<boolean> {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return false;
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
    return mapMaintenanceStatus(payload.data).maintenance;
  } catch {
    return false;
  }
}

export default async function ShopLayout({ children }: { children: React.ReactNode }) {
  if (await isStorefrontInMaintenance()) {
    redirect("/maintenance");
  }

  return <StorefrontShell>{children}</StorefrontShell>;
}
