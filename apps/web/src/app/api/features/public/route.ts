import { getApiUrl } from "@/lib/config/env";
import {
  DEFAULT_PUBLIC_FEATURE_FLAGS,
  mapPublicFeatureFlags,
  type PublicFeaturesResponse,
} from "@/lib/features/feature-availability";
import { NextResponse } from "next/server";

export async function GET() {
  const apiUrl = getApiUrl();
  if (!apiUrl) {
    return NextResponse.json(
      {
        success: true,
        data: DEFAULT_PUBLIC_FEATURE_FLAGS,
      },
      { status: 200 },
    );
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 8_000);

  try {
    const upstream = await fetch(`${apiUrl}/api/v1/features/public`, {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
      signal: controller.signal,
    });
    const payload = (await upstream.json()) as PublicFeaturesResponse;

    return NextResponse.json(
      {
        success: true,
        data: mapPublicFeatureFlags(payload),
      },
      { status: upstream.ok ? 200 : upstream.status },
    );
  } catch {
    return NextResponse.json(
      {
        success: true,
        data: DEFAULT_PUBLIC_FEATURE_FLAGS,
      },
      { status: 200 },
    );
  } finally {
    clearTimeout(timer);
  }
}
