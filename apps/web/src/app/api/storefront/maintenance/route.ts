import { getApiUrl } from "@/lib/config/env";
import {
  DEFAULT_MAINTENANCE_MESSAGE,
  mapMaintenanceStatus,
  type MaintenanceStatusPayload,
} from "@/lib/storefront/maintenance";
import { NextResponse } from "next/server";

export async function GET() {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return NextResponse.json(
      {
        success: true,
        data: {
          maintenance: false,
          message: null,
        } satisfies MaintenanceStatusPayload,
      },
      { status: 200 },
    );
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 8_000);

  try {
    const upstream = await fetch(`${apiUrl}/api/v1/storefront/maintenance`, {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
      signal: controller.signal,
    });
    const payload = (await upstream.json()) as {
      success?: boolean;
      data?: Partial<MaintenanceStatusPayload>;
      maintenance?: boolean;
      message?: string;
    };

    const mapped = mapMaintenanceStatus(payload.data ?? payload);

    return NextResponse.json(
      {
        success: true,
        data: mapped,
      },
      { status: 200 },
    );
  } catch {
    return NextResponse.json(
      {
        success: true,
        data: {
          maintenance: false,
          message: null,
        } satisfies MaintenanceStatusPayload,
        warning: DEFAULT_MAINTENANCE_MESSAGE,
      },
      { status: 200 },
    );
  } finally {
    clearTimeout(timer);
  }
}
